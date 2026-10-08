# BOXO Reusable Packaging for Magento 2

Offer [BOXO Reusable Packaging](https://www.magmodules.eu/magento2-boxo-reusable-packaging.html) in your Magento 2 checkout. During the shipping step the customer chooses between reusable packaging against a refundable deposit or ordinary disposable packaging, and the module checks in real time — against the BOXO API, on the shipping postal code — whether reusable packaging can actually be returned near them. Which products qualify is yours to decide: allow everything, exclude specific products, or restrict it to a hand-picked list, with an optional cap on how many items still fit in one container. The chosen packaging travels with the order into the admin and the order grid, so fulfilment sees it without asking.

## Features

- Reusable and disposable packaging options in the checkout shipping step
- Real-time availability check against the BOXO API, on postal code (Netherlands only)
- Deposit-based pricing for reusable packaging, charged without VAT
- Per-product eligibility rules, with an optional cap on items per container
- Optional surcharge for disposable packaging, or offer it free
- Chosen packaging on the admin order view and filterable in the order grid
- Selftest, API key test and version check from the configuration screen

## Requirements

- Magento 2.4.6 – 2.4.9
- PHP 8.1 or higher

## Installation

```bash
composer require magmodules/magento2-boxo
bin/magento module:enable Magmodules_Boxo
bin/magento setup:upgrade
bin/magento cache:flush
```

If Magento is running in production mode:

```bash
bin/magento setup:static-content:deploy
```

## Quick Start

1. Go to **Stores** > **Configuration** > **BOXO** > **Reusable Packaging**.
2. Enter your **API Key** and press **Test Connection**.
3. Set **Enable** to *Yes*. The module ships disabled.
4. Under **Checkout**, choose which products qualify and whether disposable packaging carries a surcharge.
5. Place a test order with a Dutch shipping address.

## Documentation

**English:**

**[Getting Started](docs/QUICKSTART.md)** - Get up and running in 5 minutes

**[Configuration Guide](docs/CONFIGURATION.md)** - Complete configuration reference

**[Best Practices](docs/BEST_PRACTICES.md)** - Recommended setups and examples

**[Troubleshooting](docs/TROUBLESHOOTING.md)** - Common issues and solutions

**Nederlands:**

**[Aan de slag](docs/QUICKSTART_NL.md)** - In 5 minuten aan de slag

**[Configuratie](docs/CONFIGURATION_NL.md)** - Volledige configuratie referentie

**[Best Practices](docs/BEST_PRACTICES_NL.md)** - Aanbevolen instellingen en voorbeelden

**[Probleemoplossing](docs/TROUBLESHOOTING_NL.md)** - Veelvoorkomende problemen en oplossingen

## How It Works

On installation the module creates two virtual products: a reusable packaging deposit, charged without VAT because a deposit is not a sale, and a disposable packaging fee that follows your store's tax rules.

Once the shipping address is complete, the module asks BOXO whether the postal code is serviceable. If it is, and the cart qualifies under your product rules, the options appear in the shipping step. The choice is stored on the order, and a line item is added only when the option costs money — with the surcharge disabled, disposable packaging leaves no line item at all.

Selecting in-store pickup removes the packaging line: a parcel that is never shipped needs no shipping packaging.

## Development

```bash
(cd Test && composer install)
Test/vendor/bin/phpunit -c phpunit.xml.dist
```

End-to-end tests live in `Test/End-2-end` and run Playwright against a real Magento, using a stand-in for the BOXO API so error paths can be produced on demand. Every pull request runs PHP linting, the Magento coding standard, PHPStan and the unit tests; add the `run_e2e_tests` label to run the end-to-end suite as well.

## Support

- **Product Page:** [BOXO Reusable Packaging](https://www.magmodules.eu/magento2-boxo-reusable-packaging.html)
- **Documentation:** [Magmodules Help Center](https://www.magmodules.eu/help/magento2-boxo-reusable-packaging.html)
- **Support:** [Contact Magmodules Support](https://www.magmodules.eu/support)

## License

See COPYING.txt

## Copyright

Copyright © Magmodules.eu. All rights reserved.
