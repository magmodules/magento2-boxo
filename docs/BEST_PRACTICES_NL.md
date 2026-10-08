# Best Practices

Dit zijn de aanbevolen manieren om [BOXO Herbruikbare Verpakking](https://www.magmodules.nl/magento2-boxo-reusable-packaging.html) in te richten, gebaseerd op opzetten die we in de praktijk zien werken. Er staan vier uitgewerkte voorbeelden in — een shop die het uitprobeert, een shop met een gemengde catalogus, een shop die op één merk pilot, en een multi-store opzet — plus de fouten die het vaakst voorkomen. De rode draad is dat je herbruikbare verpakking alleen moet aanbieden waar het pakket echt ingeleverd kan worden, want anders verkoop je een waarborg die de klant niet terugkrijgt.

Aanbevolen configuraties en patronen voor BOXO Herbruikbare Verpakking.

## Algemene richtlijnen

### Wel doen

- Test je API-sleutel met **Verbinding testen** voordat je de module aanzet
- Begin met *Alle producten* en beperk het pas als je echte orders ziet
- Sluit producten die er duidelijk niet in passen uit vóór je live gaat
- Stel **Maximum aantal producten** in zodra je weet wat in een container past
- Zet Debug-modus aan voordat je een probleem reproduceert, niet erna
- Draai eerst de Selftest als iets er verkeerd uitziet

### Niet doen

- Zet de module niet aan voordat er een sleutel is ingesteld — klanten zien niets, en jij denkt dat het stuk is
- Gebruik *Alleen de geselecteerde producten* niet voor een hele catalogus, tenzij je van plan bent elk product aan te vinken
- Zet geen toeslag die zo hoog is dat hij als straf leest; hij is bedoeld om te sturen, niet om af te straffen
- Laat Debug-modus niet permanent aan staan op een drukke shop — het log groeit dan zonder dat iemand het leest
- Verwacht de herbruikbare optie niet buiten Nederland. BOXO-retouren werken alleen in Nederland

---

## Veelvoorkomende scenario's

### Scenario 1: Uitprobereren

**Situatie:** Je wilt herbruikbare verpakking snel live hebben en je catalogus is redelijk uniform — kleding, boeken, cosmetica.

**Configuratie:**

Algemeen:
- Inschakelen: Ja
- API-sleutel: je productiesleutel

Checkout:
- Standaardverpakking: Herbruikbaar
- Toegestane producten: Alle producten
- Maximum aantal producten: 0
- Toeslag inschakelen: Nee

**Resultaat:** Elke Nederlandse klant in een servicegebied krijgt herbruikbare verpakking aangeboden, voorgeselecteerd, met gratis eenmalig als alternatief. Niets om per product bij te houden.

---

### Scenario 2: Een gemengde catalogus

**Situatie:** Het meeste wat je verkoopt past in een herbruikbare container, maar sommige dingen nooit — een parasol, een zak compost van 25 kg, een ingelijste spiegel.

**Configuratie:**

Checkout:
- Toegestane producten: Alle producten behalve de geselecteerde
- Maximum aantal producten: 6
- Toeslag inschakelen: Ja
- Bedrag van de toeslag: 0,35

Op de te grote producten:
- Uitsluiten van BOXO herbruikbare verpakking: Ja

**Resultaat:** Herbruikbare verpakking wordt standaard aangeboden en verdwijnt zodra een uitgesloten product of een zevende artikel in de cart komt. De toeslag maakt eenmalig een bewuste keuze in plaats van de gratis standaard.

**Tip:** Werk je catalogus door op afmeting of gewicht, niet op categorie. Categorieën vallen zelden samen met wat er in een doos past.

---

### Scenario 3: Piloten op één merk of categorie

**Situatie:** Je wilt het concept eerst bewijzen op een beperkt, beheersbaar deel van de catalogus.

**Configuratie:**

Checkout:
- Toegestane producten: Alleen de geselecteerde producten
- Standaardverpakking: Herbruikbaar

Op de producten in de pilot:
- Toestaan in BOXO herbruikbare verpakking: Ja

**Resultaat:** Alleen carts die volledig uit pilotproducten bestaan krijgen de herbruikbare optie. Al het andere gedraagt zich stil zoals eerst.

Ben je klaar om het breed te trekken, zet **Toegestane producten** dan terug op *Alle producten behalve de geselecteerde*. Je include-lijst blijft ongemoeid, dus je kunt altijd terug naar de pilot.

---

### Scenario 4: Meerdere storefronts

**Situatie:** Je draait een Nederlandse en een Duitse storefront vanuit dezelfde Magento-installatie.

**Configuratie:**

- Zet de module alleen aan op de scope van de Nederlandse website
- Laat de Duitse website uit staan

**Resultaat:** De Duitse storefront roept de BOXO API nooit aan en toont nooit een verpakkingssectie. Omdat BOXO-retouren alleen in Nederland werken, zou je daar een waarborg aanbieden die geen klant kan terugvragen.

---

## Performance

De beschikbaarheidscheck is één API-aanroep, gedaan zodra het verzendadres compleet is. Hij wordt niet gedaan voor niet-Nederlandse adressen, en helemaal niet zolang de module uit staat.

Voelt je checkout traag, dan is de BOXO-aanroep het uitsluiten waard maar zelden de oorzaak: kijk in het debug-log hoe lang het verzoek echt duurde voordat je iets anders aanpast.

## Beveiliging

Je API-sleutel wordt versleuteld opgeslagen en als sensitive gemarkeerd, waardoor hij buiten configuratiedumps blijft en buiten `app/etc/config.php` als je Magento's configuratie-export gebruikt. Plak hem niet in een supportticket — het debug-log schrijft om dezelfde reden bewust alleen een gemaskeerde versie weg.

## Veelgemaakte fouten

### Fout: De module aanzetten voordat er een sleutel is ingesteld

**Waarom het verkeerd is:** Zonder sleutel mislukt elke beschikbaarheidscheck, dus de verpakkingssectie verschijnt nooit. De module lijkt stuk terwijl hij alleen niet ingesteld is.

**Juiste aanpak:** Plak de sleutel, klik op Verbinding testen, en zet Inschakelen daarna op Ja.

---

### Fout: Een cartregel verwachten bij gratis eenmalige verpakking

**Waarom het verkeerd is:** Staat de toeslag uit, dan kost eenmalige verpakking niets en komt er terecht geen orderregel. Mensen gaan zoeken naar een regel die er niet is en concluderen dat de keuze verloren ging.

**Juiste aanpak:** Kijk naar de order zelf. De keuze staat op de order en is zichtbaar in de admin en in de order grid, met of zonder orderregel.

---

### Fout: *Alleen de geselecteerde producten* als standaard gebruiken

**Waarom het verkeerd is:** Niets komt in aanmerking tot een product is aangevinkt, dus een verse catalogus biedt herbruikbare verpakking aan niemand aan, en elk nieuw product begint stil uitgesloten.

**Juiste aanpak:** Gebruik *Alle producten behalve de geselecteerde* voor een live shop. Houd *Alleen de geselecteerde producten* voor een bewuste pilot.

---

### Fout: Maximum aantal producten instellen door cartregels te tellen

**Waarom het verkeerd is:** De limiet telt het totale aantal stuks, niet de regels. Een limiet van 3 houdt een cart met drie dezelfde t-shirts tegen, niet alleen een cart met drie verschillende producten.

**Juiste aanpak:** Zet het getal op hoeveel fysieke artikelen in een container passen, en test met meerdere stuks van hetzelfde product.

## Module-specifieke tips

Orders met in-store pickup krijgen nooit een verpakkingsregel. Een pakket dat niet verzonden wordt heeft geen verzendverpakking nodig, dus als je pickup aanbiedt hoef je daar niets voor in te stellen.

De waarborg voor herbruikbaar is btw-vrij, omdat een terugbetaalbare waarborg geen verkoop is. De toeslag voor eenmalig volgt wel je normale belastingregels. Vraagt je boekhouder waarom de een btw heeft en de ander niet, dan is dat de reden.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-boxo-reusable-packaging.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
