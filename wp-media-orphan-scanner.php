<?php
/**
 * Plugin Name: WP Media Orphan Scanner
 * Plugin URI: https://github.com/TABARC-Code/wp-media-orphan-scanner
 * Description: Audits the media library for missing files, unattached attachments and likely unused items that are just squatting in uploads.
 * Version: 1.0.0
 * Author: TABARC-Code
 * Author URI: https://github.com/TABARC-Code
 * License: GPL-3.0-or-later
 * License URI: https://www.gnu.org/licenses/gpl-3.0.html
 *
 * Why this exists:
 * The uploads folder always ends up as a landfill. Old images, unused PDFs, attachments from deleted posts,
 * files referenced by nothing. WordPress never tells you which files are actually still used.
 *
 * This plugin does a best effort scan and gives me:
 * - Attachments whose physical file is missing from disk.
 * - Attachments that are unattached.
 * - Attachments that appear unused in post content and postmeta.
 * - A basic summary so I can see how bad the damage is before I start deleting things.
 *
 * It does not delete anything for me. No trashing, no file removal. Just an audit.
 *
 * TODO: add a per media item detail view so I can inspect its references more precisely.
 * TODO: add a simple export of unused candidates as CSV.
 * FIXME: content reference detection is heuristic. I would rather be slightly conservative than over aggressive.
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

if ( ! class_exists( 'WP_Media_Orphan_Scanner' ) ) {

    class WP_Media_Orphan_Scanner {

        private $screen_slug = 'wp-media-orphan-scanner';

        public function __construct() {
            add_action( 'admin_menu', array( $this, 'add_tools_page' ) );
            add_action( 'admin_head-plugins.php', array( $this, 'inject_plugin_list_icon_css' ) );
        }

        /**
         * Shared branding icon.
         */
        private function get_brand_icon_url() {
            return plugin_dir_url( __FILE__ ) . '.branding/tabarc-icon.svg';
        }

        public function add_tools_page() {
            add_management_page(
                __( 'Media Orphan Scanner', 'wp-media-orphan-scanner' ),
                __( 'Media Orphans', 'wp-media-orphan-scanner' ),
                'manage_options',
                $this->screen_slug,
                array( $this, 'render_screen' )
            );
        }

        public function render_screen() {
            if ( ! current_user_can( 'manage_options' ) ) {
                wp_die( esc_html__( 'You do not have permission to access this page.', 'wp-media-orphan-scanner' ) );
            }

            $scan = $this->run_scan();

            ?>
            <div class="wrap">
                <h1><?php esc_html_e( 'Media Orphan Scanner', 'wp-media-orphan-scanner' ); ?></h1>
                <p>
                    This looks at a slice of the media library and asks a simple question:
                    which attachments look like they are not pulling their weight any more.
                    It will not delete anything. You still take the final swing.
                </p>

                <h2><?php esc_html_e( 'Summary', 'wp-media-orphan-scanner' ); ?></h2>
                <?php $this->render_summary( $scan ); ?>

                <h2><?php esc_html_e( 'Attachments with missing files', 'wp-media-orphan-scanner' ); ?></h2>
                <p>
                    These attachments have a database record but the file is missing from disk.
                    Users will hit broken images or downloads if you link to them.
                </p>
                <?php $this->render_missing_files( $scan ); ?>

                <h2><?php esc_html_e( 'Unattached attachments', 'wp-media-orphan-scanner' ); ?></h2>
                <p>
                    These are attachments with no post_parent. Some may be used in content,
                    some may be genuine orphans. This list is a starting point, not a hit list.
                </p>
                <?php $this->render_unattached( $scan ); ?>

                <h2><?php esc_html_e( 'Likely unused attachments', 'wp-media-orphan-scanner' ); ?></h2>
                <p>
                    These attachments are unattached and do not appear in post content or postmeta by URL.
                    The detection is conservative but not perfect. Always check before deleting.
                </p>
                <?php $this->render_unused( $scan ); ?>

                <p style="font-size:12px;opacity:0.8;margin-top:2em;">
                    <?php esc_html_e( 'On larger sites this scan looks at a limited batch of attachments per run. You can adjust the limit with the wpmos_scan_limit filter.', 'wp-media-orphan-scanner' ); ?>
                </p>
            </div>
            <?php
        }

        /**
         * Run audit over a limited set of attachments.
         */
        private function run_scan() {
            global $wpdb;

            $total_attachments = (int) $wpdb->get_var(
                "SELECT COUNT(*) FROM {$wpdb->posts} WHERE post_type = 'attachment'"
            );

            $limit = (int) apply_filters( 'wpmos_scan_limit', 500 );
            if ( $limit <= 0 ) {
                $limit = 500;
            }

            $attachments = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT ID, post_title, post_parent, post_mime_type FROM {$wpdb->posts}
                     WHERE post_type = 'attachment'
                     ORDER BY ID DESC
                     LIMIT %d",
                    $limit
                )
            );

            $missing_files = array();
            $unattached    = array();
            $unused        = array();

            foreach ( $attachments as $att ) {
                $id          = (int) $att->ID;
                $file_path   = get_attached_file( $id );
                $url         = wp_get_attachment_url( $id );

                $file_exists = $file_path && file_exists( $file_path );

                if ( ! $file_exists ) {
                    $missing_files[] = array(
                        'id'        => $id,
                        'title'     => $att->post_title,
                        'mime'      => $att->post_mime_type,
                        'file_path' => $file_path,
                        'url'       => $url,
                    );
                }

                $is_unattached = ( 0 === (int) $att->post_parent );
                if ( $is_unattached ) {
                    $unattached[] = array(
                        'id'    => $id,
                        'title' => $att->post_title,
                        'mime'  => $att->post_mime_type,
                        'url'   => $url,
                    );

                    // Only bother with unused detection for unattached items.
                    if ( $file_exists && $this->looks_unused( $id, $url ) ) {
                        $unused[] = array(
                            'id'    => $id,
                            'title' => $att->post_title,
                            'mime'  => $att->post_mime_type,
                            'url'   => $url,
                        );
                    }
                }
            }

            return array(
                'total_attachments' => $total_attachments,
                'scanned'           => count( $attachments ),
                'missing_files'     => $missing_files,
                'unattached'        => $unattached,
                'unused'            => $unused,
            );
        }

        /**
         * Heuristic to decide whether an attachment looks unused.
         *
         * I treat it as "likely unused" if:
         * - It is not referenced in any post_content by URL.
         * - It is not referenced in any postmeta value by URL.
         *
         * This is crude and can miss some cases, but that is fine.
         * I am aiming for "worth manual review", not "safe to delete blindly".
         */
        private function looks_unused( $attachment_id, $url ) {
            global $wpdb;

            $url = trim( (string) $url );
            if ( $url === '' ) {
                return false;
            }

            // Some content uses resized variants. I search by base filename as well as full URL.
            $parsed = wp_parse_url( $url );
            $path   = isset( $parsed['path'] ) ? $parsed['path'] : '';
            $basename = $path ? basename( $path ) : '';

            $like_url      = '%' . $wpdb->esc_like( $url ) . '%';
            $like_basename = $basename ? '%' . $wpdb->esc_like( $basename ) . '%' : '';

            // Look inside post_content.
            $content_hits = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->posts}
                     WHERE post_type IN ('post','page','attachment','custom_css','revision')
                     AND post_content LIKE %s",
                    $like_url
                )
            );

            if ( $content_hits > 0 ) {
                return false;
            }

            if ( $like_basename ) {
                $content_hits_loose = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->posts}
                         WHERE post_type IN ('post','page','attachment','custom_css','revision')
                         AND post_content LIKE %s",
                        $like_basename
                    )
                );
                if ( $content_hits_loose > 0 ) {
                    return false;
                }
            }

            // Look inside postmeta just in case someone stores URLs there.
            $meta_hits = (int) $wpdb->get_var(
                $wpdb->prepare(
                    "SELECT COUNT(*) FROM {$wpdb->postmeta}
                     WHERE meta_value LIKE %s",
                    $like_url
                )
            );

            if ( $meta_hits > 0 ) {
                return false;
            }

            if ( $like_basename ) {
                $meta_hits_loose = (int) $wpdb->get_var(
                    $wpdb->prepare(
                        "SELECT COUNT(*) FROM {$wpdb->postmeta}
                         WHERE meta_value LIKE %s",
                        $like_basename
                    )
                );
                if ( $meta_hits_loose > 0 ) {
                    return false;
                }
            }

            return true;
        }

        private function render_summary( $scan ) {
            $total   = (int) $scan['total_attachments'];
            $scanned = (int) $scan['scanned'];
            $missing = count( $scan['missing_files'] );
            $unattached = count( $scan['unattached'] );
            $unused  = count( $scan['unused'] );

            ?>
            <table class="widefat striped" style="max-width:800px;">
                <tbody>
                    <tr>
                        <th><?php esc_html_e( 'Total attachments in library', 'wp-media-orphan-scanner' ); ?></th>
                        <td><?php echo esc_html( $total ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Attachments scanned this run', 'wp-media-orphan-scanner' ); ?></th>
                        <td><?php echo esc_html( $scanned ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Attachments with missing files', 'wp-media-orphan-scanner' ); ?></th>
                        <td><?php echo esc_html( $missing ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Unattached attachments in scan set', 'wp-media-orphan-scanner' ); ?></th>
                        <td><?php echo esc_html( $unattached ); ?></td>
                    </tr>
                    <tr>
                        <th><?php esc_html_e( 'Likely unused attachments in scan set', 'wp-media-orphan-scanner' ); ?></th>
                        <td><?php echo esc_html( $unused ); ?></td>
                    </tr>
                </tbody>
            </table>
            <?php
        }

        private function render_missing_files( $scan ) {
            $rows = $scan['missing_files'];

            if ( empty( $rows ) ) {
                echo '<p>' . esc_html__( 'No attachments with missing files detected in this scan.', 'wp-media-orphan-scanner' ) . '</p>';
                return;
            }

            echo '<table class="widefat striped"><thead>';
            echo '<tr><th>ID</th><th>Title</th><th>Mime type</th><th>Stored path</th><th>URL</th></tr>';
            echo '</thead><tbody>';

            foreach ( $rows as $row ) {
                echo '<tr>';
                echo '<td>' . (int) $row['id'] . '</td>';
                echo '<td>' . esc_html( $row['title'] ) . '</td>';
                echo '<td>' . esc_html( $row['mime'] ) . '</td>';
                echo '<td><code>' . esc_html( $row['file_path'] ) . '</code></td>';
                echo '<td><code>' . esc_html( $row['url'] ) . '</code></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p style="font-size:12px;opacity:0.8;">' .
                 esc_html__( 'These usually come from manual file deletions or failed migrations. Clean them up in the media library or directly in the database once you are sure they are not referenced.', 'wp-media-orphan-scanner' ) .
                 '</p>';
        }

        private function render_unattached( $scan ) {
            $rows = $scan['unattached'];

            if ( empty( $rows ) ) {
                echo '<p>' . esc_html__( 'No unattached attachments in this scan set. Either everything is attached or the real mess lives further back in time.', 'wp-media-orphan-scanner' ) . '</p>';
                return;
            }

            echo '<table class="widefat striped"><thead>';
            echo '<tr><th>ID</th><th>Title</th><th>Mime type</th><th>URL</th></tr>';
            echo '</thead><tbody>';

            foreach ( $rows as $row ) {
                echo '<tr>';
                echo '<td>' . (int) $row['id'] . '</td>';
                echo '<td>' . esc_html( $row['title'] ) . '</td>';
                echo '<td>' . esc_html( $row['mime'] ) . '</td>';
                echo '<td><code>' . esc_html( $row['url'] ) . '</code></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p style="font-size:12px;opacity:0.8;">' .
                 esc_html__( 'Unattached does not automatically mean unused. Many images are inserted in content but never attached to a specific post.', 'wp-media-orphan-scanner' ) .
                 '</p>';
        }

        private function render_unused( $scan ) {
            $rows = $scan['unused'];

            if ( empty( $rows ) ) {
                echo '<p>' . esc_html__( 'No likely unused attachments detected in this scan set. Or at least nothing obvious.', 'wp-media-orphan-scanner' ) . '</p>';
                return;
            }

            echo '<table class="widefat striped"><thead>';
            echo '<tr><th>ID</th><th>Title</th><th>Mime type</th><th>URL</th></tr>';
            echo '</thead><tbody>';

            foreach ( $rows as $row ) {
                echo '<tr>';
                echo '<td>' . (int) $row['id'] . '</td>';
                echo '<td>' . esc_html( $row['title'] ) . '</td>';
                echo '<td>' . esc_html( $row['mime'] ) . '</td>';
                echo '<td><code>' . esc_html( $row['url'] ) . '</code></td>';
                echo '</tr>';
            }

            echo '</tbody></table>';
            echo '<p style="font-size:12px;opacity:0.8;">' .
                 esc_html__( 'Treat this list as suspects, not sentenced criminals. Open a few in a browser and search for the filename on the site before you start mass deletion.', 'wp-media-orphan-scanner' ) .
                 '</p>';
        }

        public function inject_plugin_list_icon_css() {
            $icon_url = esc_url( $this->get_brand_icon_url() );
            ?>
            <style>
                .wp-list-table.plugins tr[data-slug="wp-media-orphan-scanner"] .plugin-title strong::before {
                    content: '';
                    display: inline-block;
                    vertical-align: middle;
                    width: 18px;
                    height: 18px;
                    margin-right: 6px;
                    background-image: url('<?php echo $icon_url; ?>');
                    background-repeat: no-repeat;
                    background-size: contain;
                }
            </style>
            <?php
        }
    }

    new WP_Media_Orphan_Scanner();
}
