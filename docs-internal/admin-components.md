# Admin komponenty woo-core

Komponenty jsou v `assets/components.css` (načíst kdekoli přes `Admin\MenuBar::enqueue_components_style()`; na WPify stránkách automaticky).

**Pravidlo:** plugin, který komponenty používá na svých obrazovkách, je tam načte sám voláním `Admin\MenuBar::enqueue_components_style()` (ze své kopie jádra, v `admin_enqueue_scripts`). Nespoléhat na automatické načtení — když web řídí starší kopie jádra, ta obrazovky přihlášené novými filtry nezná a komponenty by zůstaly bez stylu. Volání je bezpečné i vícekrát; zaregistruje se verze řídící kopie, pokud ji má. Přepínač (`ToggleControl`) se ladí CSS proměnnými, takže varianty fungují i mimo WPify stránky. Předloha vzhledu: editor pravidel v discount rules.

## Karta

```html
<div class="wpify-card">
  <div class="wpify-card__header">
    <h4 class="wpify-card__title">Obecná nastavení</h4>
    <p class="wpify-card__description">Popis</p>
  </div>
  <div class="wpify-card__body">…</div>
  <div class="wpify-card__footer">…</div>
</div>
```

React: `<Card className="wpify-card"><CardHeader>…</CardHeader><CardBody>…</CardBody></Card>` z `@wordpress/components` — hlavičku a tělo nastyluje stejně.

## Sekce

```html
<div class="wpify-section [wpify-section--success|--danger|--warning|--info]">
  <div class="wpify-section__head">
    <span class="wpify-section__title">Priorita</span>
    <p class="wpify-section__description">Popis</p>
  </div>
  <div class="wpify-section__content">…pole…</div>
</div>
```

Přepínač uvnitř `--success` / `--danger` sekce převezme barvu sekce. Sousední sekce mají mezeru 20 px.

## Číslovaný seznam (repeater) a logické štítky

```html
<div class="wpify-repeater">
  <div class="wpify-repeater__item">
    <div class="wpify-repeater__sidebar">
      <strong>1</strong>
      <button class="wpify-icon-btn wpify-icon-btn--danger" aria-label="Smazat">…</button>
    </div>
    <div class="wpify-repeater__content">
      <span class="wpify-connector">OR with previous group</span>   <!-- od 2. položky -->
      <div class="wpify-repeater__rows">
        <div class="wpify-line">…</div>
        <div class="wpify-line"><span class="wpify-line__operator">AND</span>…</div>
      </div>
      <div class="wpify-repeater__actions">…</div>
    </div>
  </div>
</div>
```

Barvy štítků jsou proměnné na komponentě (`--wpify-connector-color/-bg/-border`, `--wpify-operator-color/-bg`), plugin je může přepsat.

## Accordion

```html
<div class="wpify-accordion">
  <div class="wpify-accordion__item">
    <button type="button" class="wpify-accordion__toggle" aria-expanded="true">
      <span class="wpify-accordion__title">Pravidlo 1</span>
      <span class="wpify-accordion__caret">▾</span>
    </button>
    <div class="wpify-accordion__content">…</div>
  </div>
</div>
```

Stav otevření drží plugin (JS/React), CSS řeší jen vzhled.

## Tooltip

```html
<span class="wpify-tooltip" data-wpify-tooltip="Text nápovědy" tabindex="0">pojem</span>
```

Čisté CSS, zobrazí se na hover i na focus z klávesnice.

## Drobné prvky

| Třída | Použití |
|---|---|
| `.wpify-toggle-compact` | obal kolem `ToggleControl` → přepínač 38×22 |
| `.wpify-icon-btn`, `--danger` | ikonové tlačítko 24×24 |
| `.wpify-spinner` | načítací kolečko v barvě textu |
| `.wpify-empty` | prázdný stav |
| `.wpify-text-muted`, `.wpify-text-error` | ztlumený text, text chyby |
| `.wpify-code-copy` | `<code>` vybratelný jedním klikem |
| `.wpify-badge-*`, `.wpify-notice-*` | štítky a hlášky (včetně `-info`) |
| `.wpify-input-quiet` | vyřazení inputu z plošného stylování — na poli nebo na obalu (např. buňka tabulky, editor s vlastními poli) |
