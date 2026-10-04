# Project UI conventions

- Use smooth expansion and collapse animations for new or modified disclosures, accordions, and collapsible panels. This is an explicit user preference.
- Animate both directions (roughly 280–320ms with ease-out), support rapid reversals without jumping, and restore natural height after completion or viewport changes.
- Respect `prefers-reduced-motion`, preserve keyboard operation and focus, and keep collapsed content out of the tab order. Do not discard entered values when collapsing.
- For native details, reuse `public/js/smooth-disclosure.js`: add `data-smooth-disclosure` to the details element and `data-disclosure-content` to its content wrapper; load the script once with `defer`. Keep native behavior usable without JavaScript. Avoid multiple animation handlers on the same element.
- Keep CSS and JavaScript in their respective asset files rather than embedding behavior or styles in Blade templates.
