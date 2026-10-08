# Changelog

## [1.0.0]

**Features:**
* Reusable and disposable packaging options in the checkout shipping step.
* Real-time availability check against the BOXO API on the shipping postal code (Netherlands only).
* Deposit-based pricing for reusable packaging, charged without VAT.
* Per-product eligibility rules: allow all products, exclude specific products or restrict to a hand-picked list, with an optional cap on items per container.
* Optional surcharge for disposable packaging, or offer it free.
* Chosen packaging shown on the admin order view and filterable in the order grid.
* Packaging artwork on the BOXO cart line, using the product mark supplied by BOXO.
* Selftest, API key test and version check from the configuration screen.
* Configuration faults are named as such instead of being reported as server errors.
* The BOXO API host can be overridden for development and testing.

**Compatibility:**
* Magento 2.4.6 and up
* PHP 8.1 and up
