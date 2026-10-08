# Troubleshooting

Having issues with [BOXO Reusable Packaging](https://www.magmodules.eu/magento2-boxo-reusable-packaging.html)? This page covers the problems that come up most, starting with a short diagnostic run-through that tells you which half of the system to look at. After that, each issue is listed with what you would see, how to fix it, and how to stop it happening again. The last section explains how to read the debug log, which answers most questions faster than guessing.

Common issues and solutions for BOXO Reusable Packaging.

## Quick Diagnostics

Work through these in order before digging deeper:

1. Run the **Selftest** under Debug & Logging. It checks the module status, the packaging products and the API connection in one go
2. Confirm **Enable** is set to *Yes* for the storefront you are testing
3. Press **Test Connection** to confirm the API key is accepted
4. Confirm the shipping country is the Netherlands and the postal code is a real one
5. Clear the cache
6. Turn on Debug Mode, reproduce the problem, and read `var/log/boxo-debug.log`

---

## Common Issues

### Issue: No packaging options appear in checkout

**Symptoms:**
- Checkout looks exactly as it did before the module was installed
- No error anywhere

**Solution:**
1. Check **Enable** is *Yes*. The module ships disabled
2. Check the shipping address is in the Netherlands. Other countries are skipped without calling the API
3. Check the postal code is valid — four digits that do not start with zero, then two letters
4. Turn on Debug Mode and reproduce. The log names the reason, including "reusable packaging not offered" with the product or quantity that caused it
5. Check the cart against your **Allowed Products** rules

**Prevention:** Place a test order after any change to the product rules. One newly excluded product is enough to withdraw the option for a whole cart.

---

### Issue: The reusable option disappeared for one customer's cart

**Symptoms:**
- Options appear for some carts and not others
- The customer says it worked yesterday

**Solution:**
1. Look at what is in the cart. A single disallowed product withdraws the reusable option for the entire order
2. Count the items. If **Maximum Products** is set and the total quantity exceeds it, the option is withheld
3. The debug log records both cases with the exact product or quantity

**Prevention:** This is the module working as intended. If it happens more than you expect, your exclusion list or your cap is stricter than you meant it to be.

---

### Issue: Test Connection says the key was rejected

**Symptoms:**
- "The BOXO API rejected this key"

**Solution:**
1. Check the key for a copied space or a missing character
2. Confirm with BOXO that the key is active
3. Confirm you are using the key issued for this shop

**Prevention:** Use Test Connection whenever you change the key. It tests what is in the field, so you find out before saving.

---

### Issue: Test Connection cannot reach the API

**Symptoms:**
- "Could not reach the BOXO API, see the error log for details"

**Solution:**
1. Check `var/log/boxo-error.log` for the underlying message
2. Check outbound HTTPS from the shop is allowed. Hosting with an outbound firewall or proxy often blocks it
3. Retry in a few minutes — this is also what a BOXO outage looks like

**Prevention:** Nothing in the module causes this. Ask your hosting party to allow outbound traffic to the BOXO API, permanently.

---

### Issue: Selecting an option returns an error

**Symptoms:**
- An error under the packaging options saying the packaging product is not available in this store
- The selection does not stick

**Solution:**
1. Run the Selftest and look at the packaging products check
2. Open the two products in Catalog → Products. They are created on installation and are not visible individually
3. Check they are assigned to the website you are testing, enabled, and in stock in the source that serves it
4. If they are missing entirely, the module's setup scripts have not run on this installation — ask your developer or hosting party to complete the module upgrade

**Prevention:** Check the packaging products after adding a new website. Products assigned only to the original website cannot be added to a cart on the new one.

---

### Issue: Single-use packaging is not charged

**Symptoms:**
- The customer picks single-use and pays nothing for it
- No packaging line in the order

**Solution:**
1. Check **Enable Surcharge** is set to *Yes*
2. Check **Surcharge Amount** is greater than zero

With the surcharge off, this is correct behaviour: the option is free and deliberately adds no line item.

**Prevention:** If you want it charged, confirm on a test order rather than from the configuration screen alone.

---

### Issue: The chosen packaging is missing from the order grid

**Symptoms:**
- The column is empty for some orders

**Solution:**
1. Orders placed before the module was installed have no value and correctly show an empty cell
2. For recent orders, check the column is enabled in the grid's column selector
3. If the order detail page shows a choice and the grid does not, reindex the sales order grid

**Prevention:** Nothing to prevent for historical orders — that cell stays empty by design.

---

### Issue: Packaging appears on an in-store pickup order

**Symptoms:**
- A pickup order carries a packaging line

**Solution:**
1. Confirm the order really used in-store pickup rather than a shipping method named like it
2. Check the debug log around the shipping step
3. Report it with the order increment id — the module removes the packaging line when pickup is selected, so this should not happen

**Prevention:** This is not expected behaviour and is worth a support ticket rather than a workaround.

---

## Debug Mode

Turn it on under Stores → Configuration → BOXO → Reusable Packaging → Debug & Logging.

Two logs are written:

- `var/log/boxo-debug.log` — API requests and responses, availability checks, selection changes, and the reason the reusable option was withheld. Written only when Debug Mode is on
- `var/log/boxo-error.log` — failures. Always written

Both can be read from the admin with the **Debug Check** and **Error Check** buttons, so you do not need shell access.

What to look for:

- `checkServiceAvailable request` and its response, with the status BOXO returned — this tells you whether the API answered and what it said
- `reusable packaging not offered` with a product id or a quantity — this is the eligibility rules working, and names the cause
- `api_key_masked` shows only the first and last four characters of your key. That is intentional; the full key is never logged

Turn Debug Mode off again when you are done. On a busy shop the log grows quickly and nothing reads it.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-boxo-reusable-packaging.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
