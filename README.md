# wp-media-orphan-scanner

<p align="center">
  <img src=".branding/tabarc-icon.svg" width="180" alt="TABARC-Code Icon">
</p>

# WP Media Orphan Scanner

The uploads folder starts out innocent.  
Then time happens.

Drafts. Deleted posts. Abandoned landing pages. Demo content.  
Every one of them leaves files behind. WordPress never complains. Disk usage climbs forever.

This plugin does not magically clean that mess. It just shines a torch on it.

It scans a chunk of your media library and tells you:

- Which attachments have missing files on disk  
- Which attachments are unattached to any post  
- Which unattached attachments do not seem to be referenced in content or meta  

Then it leaves the decisions to you.

## What it actually does

When you visit Tools  
Media Orphans, it:

1. Counts how many attachments exist in total  
2. Loads a batch of the most recent attachments  
3. For each one, checks:
   - Does the file exist on disk  
   - Is it attached to a parent post  
   - Does its URL or filename appear in post content  
   - Does its URL or filename appear in postmeta  

From that, it builds three lists.

### Attachments with missing files

These are attachments where:

- The database entry exists  
- `get_attached_file` points at a path  
- That path does not exist on disk  

Usually caused by:

- Manual file deletions via FTP  
- Migrations that forgot to copy everything  
- Overenthusiastic cleanups  

These are broken by definition. They might still have URLs in content, but the file is gone.

### Unattached attachments

These are attachments with `post_parent` set to zero.

That does not mean they are unused. It just means:

- They are not directly attached to a post  
- They may have been uploaded via the media library directly  
- They may have been inserted into content but never attached  

This list is there so you can see how many floating items your library has accumulated.

### Likely unused attachments

These are the interesting ones.

Each one is:

- Unattached  
- Has a file that still exists  
- Does not appear in any `post_content` by full URL  
- Does not appear in any `post_content` by base filename  
- Does not appear in any `postmeta` value with the URL or base filename  

In other words, the plugin cannot find any obvious reference to it in standard content tables.

That does not mean nobody uses it. A front end framework, page builder, custom table or some third party integration might still reference it. So this list is a set of suspects, not a list of safe deletions.

## What it does not do

Important part.

It does not:

- Delete attachments  
- Remove files from disk  
- Move files  
- Touch the database beyond running selects  
- Try to parse builder specific shortcodes or layout configs  

It is an audit tool only.

If you want automated deletion, write your own script, then curse when something breaks, then come back and use this first next time.

## Requirements

- WordPress 6.0 or newer  
- PHP 7.4 or newer  
- Ability to run database queries on the posts and postmeta tables  
- Administrator level access  

On large sites the scan is limited to a batch of attachments per run to avoid locking things up.

## Installation

Clone or download the repository:

```bash
cd wp-content/plugins/
git clone https://github.com/TABARC-Code/wp-media-orphan-scanner.git
Activate it:

Go to Plugins

Activate WP Media Orphan Scanner

Then open:

Tools

Media Orphans

Scan limits and performance
By default the plugin inspects at most 500 attachments per run, starting from the most recent ones.

If you want to change this:

php
Copy code
add_filter( 'wpmos_scan_limit', function( $limit ) {
    return 1000; // or whatever your server can tolerate
} );
Every attachment gets a handful of queries behind the scenes:

Check file path

Search posts for URL and filename

Search postmeta for URL and filename

This is acceptable for a manual tool, but you would not run it on every page load.

How to use it without tanking a site
Basic survival pattern:

Run the scan with the default limit

Look at:

How many attachments exist in total

How many have missing files

How many are unattached

How many look unused

Focus first on attachments with missing files

They are already broken

Fix references or remove the attachments

For likely unused attachments:

Pick a few at random

Search for the filename and URL in your front end

Confirm nothing obviously uses them

Only then think about using bulk operations in the media library or direct database work.

On very large sites, increase the scan limit slowly and watch query performance in your logs.

Limitations and caveats
This is not a forensic tool. It has blind spots by design.

It does not see:

References stored in custom tables

References inside proprietary page builder blobs that encode things in obtuse ways

References inside cached HTML outside the database

Off site systems pointing directly at your media URLs

It also has some false positive protection:

It searches by both full URL and base filename

It checks both posts and postmeta

That helps catch resized variants and some builder usage, but it is not perfect.

The point is simple:

Show you where obvious waste lives

Help you prioritise

Stop you from pretending uploads is fine

When this is useful
Good times to reach for this:

Before a migration to new hosting

Before a big media cleanup

After a site redesign, when you know entire sections of content died

When backups and disk usage start to hurt

When client sites have ten thousand stock images and nobody knows what is still live

It is not something you need enabled forever. It is a maintenance tool. Run it, clean things up, deactivate if you like.

Roadmap
Things that might crop up later:

Export of suspected unused attachments as CSV

Per attachment detail view showing counts of content references

Filters for mime types

Separate modes for images versus documents

## Things that probably will not:##

Automatic deletion

Background batch deletions

Trying to introspect every page builder storage format

If I ever add deletion, it will be with aggressive warnings and disabled by default - I do not need the follow up crap.


Audits the media library for missing files, unattached attachments and likely unused items that are just squatting in uploads. 
