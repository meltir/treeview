# treeview

Simple CLI utility to import a json directory listing into a database, regardless of the listing size.

Amend constructor to change database backend, by default uses sqlite.

Followup uses of this database are fast (once indexed) search and identifying deduplication candidates.

Borne out of the frustration of a data hoarder, needing easy offline indexes of various HDD archives.

Commercial tools exist, but are not free. Free tools exist but fail to create images of very large drives.

To generate a directory listing in json format, use the following command:

```
tree -JspaguD <folder to index> -o <filename>.json
```

Requires PHP 7.2.

Usage and setup:

```
composer install
php import.php <dir-index>.json
```

This will generate a sqlite database in the same directory as the script, named <dir-index>.db

This is a side project and not supported in any way. Use at your own risk.
