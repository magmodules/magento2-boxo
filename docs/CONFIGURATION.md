# Configuration Guide

Here is where you will find all the settings for [BOXO Reusable Packaging](https://www.magmodules.eu/magento2-boxo-reusable-packaging.html). This guide explains what each option does and when you would want to use it, organised the same way as the admin panel so you can find things quickly. The settings that change what a customer is charged — the reusable deposit and the disposable surcharge — are covered in the most detail, along with the product rules that decide which carts get the reusable option at all.

Complete reference for all BOXO Reusable Packaging configuration options.

**Location:** Stores → Configuration → BOXO → Reusable Packaging

## General

### API Key

Your key from BOXO. It is stored encrypted and is never shown back to you in full.

### Test Connection

Checks the key against BOXO and tells you what it found. It tests what is currently in the API Key field, so you can verify a key before saving it.

The three answers mean different things and it is worth knowing which is which:

- **Connection established** — the key works
- **Rejected this key** — BOXO answered, and said no. Check the key, or whether it has been activated
- **Could not reach the API** — nothing answered. A firewall, an outbound proxy or an outage. Your key is probably fine

## Checkout

### Default Packaging

Which option is preselected when the packaging section appears. Reusable is preselected by default, which is the point of the module — the customer has to actively opt out.

**When to use:** Set it to single-use if you are introducing reusable packaging carefully and would rather customers choose it deliberately than find it already selected.

### Allowed Products

Decides which products may be shipped in reusable packaging.

- **All products** — everything qualifies, no list is consulted
- **All products except selected** — everything qualifies except products you tick
- **Only selected products** — nothing qualifies unless you tick it

The list lives on the product, as a checkbox under the BOXO section: *Exclude from BOXO reusable packaging* for the first mode, *Allow in BOXO reusable packaging* for the second. They are two separate fields on purpose, so you can mark your exclusions, try *Only selected products* for a while, and switch back without having lost your list.

**A single disallowed product withdraws the reusable option for the whole cart.** The order ships as one parcel, so the strictest product in it wins. A customer who adds one oversized item will see the reusable option disappear.

**When to use:**
- *All products* for a catalogue of broadly similar items
- *All products except selected* when a handful of products are too large, too heavy or too awkward
- *Only selected products* when you want to pilot reusable packaging on part of the catalogue

### Maximum Products

The largest number of items that still fits in one reusable container. Leave it at 0 for no limit.

This counts total quantity, not cart lines: five of one product fills the box exactly as much as five different ones. The packaging item itself is not counted, and the rows of a configurable or bundle product are counted through their parent rather than twice.

**When to use:** Set it once you know what actually fits. A shop selling small items might never need it; a shop selling shoes will.

### Enable Surcharge and Surcharge Amount

Whether single-use packaging costs the customer anything, and how much.

Leave the surcharge off and single-use packaging is offered free. It then adds no line to the cart at all — the customer is not shown a nil amount in their order summary, and their choice is still recorded on the order.

Turn it on and the amount is charged as a normal, taxable line item, following your store's tax configuration.

**When to use:** A small surcharge is the usual way to make reusable packaging the attractive option without refusing single-use outright.

### Information Page URL

Where the info icon next to the reusable option links to. Leave it empty and customers go to the BOXO return locations page, which is what most shops want.

**When to use:** Point it at your own page if you explain your returns process in your own words.

## Checkout wording

The text customers read in checkout is not configurable, and that is deliberate: the wording belongs to the BOXO proposition and should read the same in every shop. If you need it in another language, translate it through a locale CSV rather than retyping it per store — that keeps a multi-language shop properly localised instead of freezing one typed string into every language.

## Debug & Logging

### Debug Mode

Writes API calls, availability checks and selection updates to `var/log/boxo-debug.log`. Errors are always logged to `var/log/boxo-error.log`, whether debug mode is on or not.

Turn it on **before** reproducing a problem. It cannot recover what an earlier run would have written.

### Debug and Error Check

Shows the last lines of either log in a modal, so you can look at them without shell access.

### Selftest

Runs a handful of checks and reports them in one place: your PHP version, your Magento version, whether the module is enabled, whether the two BOXO packaging products exist and can be sold, whether the API answers, and which version of the extension you are on.

It is the fastest first step when something is not working, because it distinguishes "the module is misconfigured" from "the module is fine and something else is wrong".

## What the module charges

Two products are created when the module is installed:

| What | Price | Tax |
|---|---|---|
| Reusable packaging deposit | 3.95 | None — a deposit is not a sale, so no VAT |
| Single-use packaging | Your surcharge, or free | Taxable goods, following your store's tax rules |

The deposit amount is set by BOXO, not by you. The surcharge is yours.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-boxo-reusable-packaging.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
