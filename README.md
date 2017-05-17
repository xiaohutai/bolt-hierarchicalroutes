# Hierarchical Routes

Heh... The term "Hierarchical Routes" makes no sense.

A simple way to get hierarchical content to work by using `menu.yml`.
No assumptions or crazy features.


## Configuration

- `menu`: select which menu to check for as the hierarchical tree
  - todo: allow for more than 1 menu?
  - todo: how to deal with duplicates?
- `rules`: apply some sets of content under a (parent) node
  - todo: allow for `contenttype`?
  - todo: allow for `query`?
- `cache`: enable caching


## Twig functions

- `getParent(record)` - Returns the parent of the current record, otherwise `null`.
- `getParents(record)` - Returns an array of all the parents of the current record. Useful for breadcrumbs: iterate over `getParents(record)|reverse`.
- `getChildren(record)` - Returns an array of all the children of the current record.
- `getSiblings(record)` - Returns an array of all the siblings of the current record.


## See also

- https://github.com/bolt/bolt/issues/1295
- https://github.com/bolt/bolt/issues/5989
