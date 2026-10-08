# Quick Start Guide

This is the quick start guide for [BOXO Reusable Packaging](https://www.magmodules.eu/magento2-boxo-reusable-packaging.html). We will get you offering reusable packaging in checkout in about five minutes. It covers the three things you actually need — your API key, switching the module on, and deciding which products qualify — and finishes with a test order so you can see the options appear for a real Dutch address. Everything else, from quantity caps to surcharges, can wait until this works.

Get BOXO Reusable Packaging running in 5 minutes.

## Prerequisites

- Module installed and cache cleared
- An API key from your BOXO account
- A product in your catalogue you can order with a Dutch shipping address

## Step 1: Connect to BOXO

Navigate to: Stores → Configuration → BOXO → Reusable Packaging → General

Paste your key into **API Key**, then press **Test Connection**. You do not have to save first — the button checks what is in the field.

You want to read "Connection to the BOXO API established". If you get "rejected", the key is wrong or not active yet. If you get "could not reach", something between your shop and BOXO is blocking the request.

## Step 2: Switch the module on

Set **Enable** to *Yes* and save.

The module ships disabled, so nothing appears in checkout until you do this.

## Step 3: Decide which products qualify

Navigate to: Stores → Configuration → BOXO → Reusable Packaging → Checkout

For a first run, leave **Allowed Products** on *All products*. Reusable packaging is then offered for every cart.

If you already know some products will never fit — a garden bench, a crate of tiles — set it to *All products except selected* and tick **Exclude from BOXO reusable packaging** on those products. One excluded product in the cart withdraws the reusable option for the whole order, because the order ships as one parcel.

## Step 4: Place a test order

Add a product to the cart and go to checkout with a Dutch shipping address, for example Teststraat 1, 1012 AB Amsterdam.

Once the address is complete you should see a packaging section in the shipping step with two options. Pick the reusable one and check that the deposit appears in the order summary.

Place the order and open it in Sales → Orders. The chosen packaging is shown on the order and in the order grid, so fulfilment can see it without asking.

## What if nothing appears?

Three things account for almost every case: the module is still disabled, the shipping country is not the Netherlands, or the postal code is not serviceable by BOXO. Turn on Debug Mode under Debug & Logging and the reason is written to `var/log/boxo-debug.log`.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-boxo-reusable-packaging.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
