# Changelog

## Version 1.0.11

- Generalize feed settings logic:
    - support multiple feed types,
    - add per-type settings,
    - refactor the admin settings structure.
- Validate enabled feed.
- Get locale data from WooCommerce.

## Version 1.0.10

- Add product condition support in Facebook export.

## Version 1.0.9

- Update export logic to include rich descriptions.
- Handle empty product arrays.
- Support additional product types

## Version 1.0.8

- Fix Facebook export functionality.
- Refine Google export XML logic.

## Version 1.0.7

- Refactor shared export logic for Facebook and Google parsers.
- Improve XML generation consistency.

## Version 1.0.6

- Generalize shared export logic.
- Add Facebook export functionality.
- Add condition for variation parent is published.

## Version 1.0.4

- Refactor and enhance Google export functionality with optimized XML generation, excluded product handling, shipping
  cost calculation, and XMLWriter usage.
- Update composer requirements.

## Version 1.0.3

- Refactor term retrieval to store term names.

## Version 1.0.1

- Enhance Google export XML generation with customizable title, link, and description from settings, and refine feed
  settings configuration.

## Version 1.0

- Initial release with basic functionality.
- Refactor namespaces for WooCommerce Feed module and enhance Google export functionality with partitioned XML
  generation and shipping method processing.