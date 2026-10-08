# Snelstartgids

Dit is de snelstartgids voor [BOXO Herbruikbare Verpakking](https://www.magmodules.nl/magento2-boxo-reusable-packaging.html). We zorgen dat je binnen ongeveer vijf minuten herbruikbare verpakking in je checkout aanbiedt. Deze pagina behandelt de drie dingen die je echt nodig hebt — je API-sleutel, de module aanzetten, en bepalen welke producten in aanmerking komen — en sluit af met een testorder, zodat je de opties ziet verschijnen bij een echt Nederlands adres. Al het andere, van een maximum aantal producten tot een toeslag, kan wachten tot dit werkt.

Krijg BOXO Herbruikbare Verpakking in 5 minuten werkend.

## Voorwaarden

- Module geïnstalleerd en cache geleegd
- Een API-sleutel uit je BOXO-account
- Een product in je catalogus dat je met een Nederlands verzendadres kunt bestellen

## Stap 1: Verbinding met BOXO

Ga naar: Winkels → Configuratie → BOXO → Herbruikbare verpakking → Algemeen

Plak je sleutel in **API-sleutel** en klik op **Verbinding testen**. Je hoeft niet eerst op te slaan — de knop controleert wat er in het veld staat.

Je wilt "Verbinding met de BOXO API tot stand gebracht" lezen. Krijg je "geweigerd", dan is de sleutel verkeerd of nog niet geactiveerd. Krijg je "niet bereiken", dan blokkeert er iets tussen je shop en BOXO het verzoek.

## Stap 2: De module aanzetten

Zet **Inschakelen** op *Ja* en sla op.

De module staat standaard uit, dus er verschijnt niets in de checkout tot je dit doet.

## Stap 3: Bepaal welke producten in aanmerking komen

Ga naar: Winkels → Configuratie → BOXO → Herbruikbare verpakking → Checkout

Laat **Toegestane producten** voor een eerste run op *Alle producten* staan. Herbruikbare verpakking wordt dan bij elke cart aangeboden.

Weet je al dat sommige producten er nooit in passen — een tuinbank, een pak tegels — zet het dan op *Alle producten behalve de geselecteerde* en vink **Uitsluiten van BOXO herbruikbare verpakking** aan bij die producten. Eén uitgesloten product in de cart haalt de herbruikbare optie voor de hele order weg, omdat de order als één pakket verzonden wordt.

## Stap 4: Plaats een testorder

Zet een product in de cart en ga naar de checkout met een Nederlands verzendadres, bijvoorbeeld Teststraat 1, 1012 AB Amsterdam.

Zodra het adres compleet is, zie je in de verzendstap een verpakkingssectie met twee opties. Kies de herbruikbare optie en controleer of het waarborgbedrag in het besteloverzicht staat.

Plaats de order en open hem via Verkopen → Orders. De gekozen verpakking staat op de order en in de order grid, zodat fulfilment het kan zien zonder ernaar te vragen.

## En als er niets verschijnt?

Drie dingen verklaren bijna elk geval: de module staat nog uit, het verzendland is niet Nederland, of de postcode valt buiten het BOXO-servicegebied. Zet Debug-modus aan onder Debug & logging en de reden wordt weggeschreven naar `var/log/boxo-debug.log`.

---

## Meer Hulp Nodig?

**Documentatie:**
- [Alle Help Artikelen](https://www.magmodules.nl/help/magento2-boxo-reusable-packaging.html) - Compleet documentatie overzicht

**Support:**
- [Contact Opnemen](https://www.magmodules.nl/support) - Hulp van ons team
