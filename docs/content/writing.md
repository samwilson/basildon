---
title: Basildon
subtitle: Writing
---

Basildon supports bulk writing of metadata into Markdown frontmatter.

All the metadata from content files is read into an [SQLite](https://www.sqlite.org) database when a site is built,
and this database can be opened in any other programme and edited.
After being saved to the database, the `build` command can be run to update the `content/` directory files.

The whole workflow should be something like:

1. Make sure your site is under version control, and has no outstanding changes
   (to make it easier to track changes that will be made by Basildon).
2. Build your site: `./vendor/bin/basildon build .`
3. Edit the database at `./cache/database/db.sqlite3`
   using a programme such as [DB Browser for SQLite](https://sqlitebrowser.org/),
   and write the changes back to the same file.
4. Run `./vendor/bin/basildon write .`
5. Check the changes before committing them.
