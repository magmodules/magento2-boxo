# Best Practices

These are the recommended ways to set up [BOXO Reusable Packaging](https://www.magmodules.eu/magento2-boxo-reusable-packaging.html), based on the setups we have seen work. There are four worked examples — a shop trying it out, a shop with a mixed catalogue, a shop piloting on one brand, and a multi-store setup — plus the mistakes that come up most often. The theme running through all of it is that reusable packaging has to be offered only where the parcel can actually be returned, otherwise you are selling a deposit the customer cannot get back.

Recommended configurations and patterns for BOXO Reusable Packaging.

## General Guidelines

### Do's

- Test your API key with **Test Connection** before enabling the module
- Start with *All products*, then narrow it down once you see real orders
- Exclude products that obviously will not fit before you go live
- Set **Maximum Products** once you know what fits in a container
- Turn on Debug Mode before reproducing a problem, not after
- Run the Selftest first when something looks wrong

### Don'ts

- Do not enable the module before a key is configured — customers see nothing, and you will think it is broken
- Do not use *Only selected products* for a whole catalogue unless you intend to tick every product
- Do not set a surcharge so high it reads as a penalty; it is meant to nudge, not punish
- Do not leave Debug Mode on permanently on a busy shop — the log grows for no reason
- Do not expect the reusable option outside the Netherlands. BOXO returns are Dutch-only

---

## Common Scenarios

### Scenario 1: Trying it out

**Use case:** You want reusable packaging live quickly and your catalogue is fairly uniform — clothing, books, cosmetics.

**Configuration:**

General:
- Enable: Yes
- API Key: your production key

Checkout:
- Default Packaging: Reusable
- Allowed Products: All products
- Maximum Products: 0
- Enable Surcharge: No

**Result:** Every Dutch customer in a serviceable area is offered reusable packaging, preselected, with free single-use as the alternative. Nothing to maintain per product.

---

### Scenario 2: A mixed catalogue

**Use case:** Most of what you sell fits a reusable container, but some things never will — a parasol, a 25 kg bag of compost, a framed mirror.

**Configuration:**

Checkout:
- Allowed Products: All products except selected
- Maximum Products: 6
- Enable Surcharge: Yes
- Surcharge Amount: 0.35

On the oversized products:
- Exclude from BOXO reusable packaging: Yes

**Result:** Reusable packaging is offered by default and withdrawn as soon as an excluded product or a seventh item enters the cart. The surcharge makes single-use the deliberate choice rather than the free default.

**Tip:** Work through your catalogue by dimension or weight rather than by category. Categories rarely line up with what fits in a box.

---

### Scenario 3: Piloting on one brand or category

**Use case:** You want to prove the concept on a limited, controlled part of the catalogue before rolling it out.

**Configuration:**

Checkout:
- Allowed Products: Only selected products
- Default Packaging: Reusable

On the products in the pilot:
- Allow in BOXO reusable packaging: Yes

**Result:** Only carts made up entirely of pilot products get the reusable option. Everything else silently behaves as before.

When you are ready to go wide, switch **Allowed Products** back to *All products except selected*. Your include list is left untouched, so you can return to the pilot at any time.

---

### Scenario 4: Multiple storefronts

**Use case:** You run a Dutch storefront and a German one from the same Magento installation.

**Configuration:**

- Enable at the Dutch website scope only
- Leave the German website disabled

**Result:** The German storefront never calls the BOXO API and never shows a packaging section. Since BOXO returns only work in the Netherlands, enabling it there would offer a deposit no customer could reclaim.

---

## Performance

The availability check is one API call, made when the shipping address is complete. It is not made for non-Dutch addresses, and not made at all while the module is disabled.

If your checkout feels slow, the BOXO call is worth ruling out but is rarely the cause: check the debug log for how long the request actually took before changing anything else.

## Security

Your API key is stored encrypted and marked sensitive, which keeps it out of configuration dumps and out of `app/etc/config.php` when you use Magento's configuration export. Do not paste it into a support ticket — the debug log deliberately writes only a masked version, for the same reason.

## Common Mistakes

### Mistake: Enabling the module before configuring a key

**Why it's wrong:** Without a key every availability check fails, so the packaging section never appears. The module looks broken while it is merely unconfigured.

**Correct approach:** Paste the key, press Test Connection, and only then set Enable to Yes.

---

### Mistake: Expecting a cart line for free single-use packaging

**Why it's wrong:** When the surcharge is off, single-use packaging costs nothing and correctly adds no line item. People go looking for a row that is not there and conclude the choice was lost.

**Correct approach:** Look at the order itself. The choice is recorded on the order and shown in the admin and in the order grid, with or without a line item.

---

### Mistake: Using *Only selected products* as a default

**Why it's wrong:** Nothing qualifies until a product is ticked, so a fresh catalogue offers reusable packaging to nobody, and every new product silently starts out excluded.

**Correct approach:** Use *All products except selected* for a live shop. Keep *Only selected products* for a deliberate pilot.

---

### Mistake: Setting Maximum Products by counting cart lines

**Why it's wrong:** The cap counts total quantity, not lines. A cap of 3 stops a cart of three t-shirts, not just a cart of three different products.

**Correct approach:** Set the number to how many physical items fit in a container, and test with several of the same product.

## Module-Specific Tips

In-store pickup orders never get a packaging line. A parcel that is not shipped needs no shipping packaging, so if you offer pickup you do not have to configure anything for it.

The reusable deposit carries no VAT, because a refundable deposit is not a sale. The single-use surcharge does follow your normal tax rules. If your accountant asks why one has tax and the other does not, that is the reason.

---

## Need More Help?

**Documentation:**
- [All Help Articles](https://www.magmodules.eu/help/magento2-boxo-reusable-packaging.html) - Complete documentation overview

**Support:**
- [Contact Support](https://www.magmodules.eu/support) - Get help from our team
