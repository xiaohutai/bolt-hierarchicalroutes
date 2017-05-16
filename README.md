# Hierarchical Routes

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

- `getParents(record)`
- `getChildren(record)`
- `getSiblings(record)`


## See also

- https://github.com/bolt/bolt/issues/1295
- https://github.com/bolt/bolt/issues/5989
