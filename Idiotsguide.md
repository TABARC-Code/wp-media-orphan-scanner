## `IdiotsGuide.md`

```markdown
# IdiotsGuide  
WP Media Orphan Scanner

This is for the version of me who just sees a number in the Media Library that feels wrong and wants to know which files are actually being used, without reading database diagrams.

No theory. Just practical steps.

## What WordPress calls an attachment

Every image, PDF, video or random file you upload gets:

- A post of type `attachment` in the database  
- A file on disk in `wp-content/uploads/...`  
- A URL pointing at that file  

Sometimes that attachment is attached to a parent post.  
Sometimes it is free floating.

Over years of work you end up with three categories:

- Used and healthy  
- Broken (database says file exists, disk says no)  
- Possibly unused (nobody seems to reference them)  

This plugin helps you find the last two.

## What the scan is actually doing, in plain terms

When you open Tools  
Media Orphans, the plugin:

1. Looks at how many attachments exist in total  
2. Takes a batch of the most recent ones  
3. For each attachment in that batch:
   - Checks whether the file exists on disk  
   - Checks whether it is attached to a post  
   - Searches posts and pages for the attachment URL  
   - Searches postmeta for the attachment URL  
   - Also searches for just the filename, to catch resized versions  

If it cannot find anything that references that file, it marks it as "likely unused".

Nothing is deleted at this stage. It is just building three lists.

## Those three lists

### 1. Missing files

These are attachments whose file is gone.

It shows you:

- The attachment id  
- The title  
- The mime type  
- The stored path  
- The URL WordPress thinks exists  

If a user or content references that URL, they will see nothing or a generic browser error.

These are safe to either:

- Replace with a fresh file  
- Remove from the media library  
- Clean aggressively after checking old posts if the site has a long memory  

They are already broken. You are not going to make them more broken.

### 2. Unattached attachments

These have no `post_parent`.

They are not necessarily unused. They might be:

- Logo files used in theme options  
- Images dropped into content but never attached  
- Assets used by a page builder  

Think of this list as:

> Here is everything the attachment system has lost track of structurally.

Useful for understanding scale, not for deletion.

### 3. Likely unused attachments

These are the ones you care about, and the ones you should be nervous with.

Each one is:

- Unattached  
- Has a file that still exists  
- Cannot be found in any post content by URL  
- Cannot be found in any post content by filename  
- Cannot be found in postmeta by URL or filename  

In other words, the plugin tried a few reasonable ways to catch references and failed.

That makes them candidates for deletion, not guaranteed safe targets.

## How to use this without causing fires

### Step 1  
Run a scan with the default settings.

Look at the summary:

- Total attachments  
- How many scanned  
- How many have missing files  
- How many are unattached  
- How many look unused  

You now know roughly how bad the library has become.

### Step 2  
Deal with attachments that have missing files.

These are already broken. For each:

- Decide whether the missing file should be restored  
- Or whether to remove the attachment so the media library does not lie  

This is the lowest risk category.

### Step 3  
Sample the likely unused list.

Do not bulk delete. Yet.

Pick a few candidates and:

- Open their URL in a private browser window  
- Search the site front end for the filename  
- Check any obvious templates where they might appear  

If multiple samples genuinely appear unused, you can start treating this list more seriously.

If you find false positives, dial back your aggression.

### Step 4  
Use the media library for deletion.

Once you are confident:

- Filter the Media Library by the same time range or mime type  
- Delete from there, not by direct database edits  

That way you get the normal WordPress cleanup behaviour.

## Things that will trick the scanner

The detection is deliberately simple.

It can be fooled by:

- Page builders that store references in their own tables  
- Custom plugins storing URLs in non standard places  
- Shortcodes that reference files by ID only, without a URL  
- External tools pulling files directly from the uploads folder  

Because of that, any list of "likely unused" attachments should be treated as untrusted until you do some sampling.

If a plugin handles a lot of your layout, check its docs and maybe run a test delete on staging first.

## Adjusting how much it scans

On huge sites, 500 attachments per run might not move the needle.

You can change the scan size with:

```php
add_filter( 'wpmos_scan_limit', function( $limit ) {
    return 1500;
} );
Do not go from 500 to 50 000 in one go.
Increase slowly and watch how long the page takes to load.

This scan is running a bunch of SQL behind the scenes. Respect that.

When this is genuinely worth your time
Use this tool when:

Disk usage matters

Backup times are getting silly

You are preparing a big move to new hosting

You are trying to untangle a decade old project someone left you

You need convincing evidence to tell a client their media library is a landfill

## Do not bother when: ##

The site is new

There are only a few hundred media items

You have bigger fires to put out

Final bit of sanity
The plugin is annoyingly honest.

It does not give you a big friendly delete button. It gives you:

Data about missing files

Data about unattached items

Best effort guesses on likely unused files

Then expects you to make adult decisions.

If that makes you slightly uncomfortable, good. It should.
