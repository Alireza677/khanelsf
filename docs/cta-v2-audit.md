# CTA v2 audit

The legacy CTA block stores flat JSON and supports `classic` and `image` templates. Content fields are `eyebrow`, `title`, `description`, primary/secondary button label and URL, and `background_image`. Presentation fields are heading tag, alignment, section background, content width, and desktop/mobile background-image sizing.

Before v2, Page and Template resources maintained separate copies of the CTA schema, while the Blade view read legacy keys directly. CTA had stable block identity support but no contract normalization or canonical save shape.

CTA v2 retains only those existing capabilities. It uses the shared block registry, identity lifecycle, editor hydrator, and save manager; it adds no CTA-specific persistence, queries, rollout system, or new visual options.

## CTA Contract v2

The persisted and rendered canonical shape is:

```json
{
  "block_id": "01ARZ3NDEKTSV4RRFFQ69G5FAV",
  "schema_version": 2,
  "template": "classic",
  "content": {
    "eyebrow": null,
    "title": null,
    "description": null,
    "primary_cta": {
      "label": null,
      "action": { "type": "url", "url": null, "form_id": null, "display": null }
    },
    "secondary_cta": {
      "label": null,
      "action": { "type": "url", "url": null, "form_id": null, "display": null }
    },
    "media": { "url": null }
  },
  "settings": {
    "heading_tag": "h2",
    "alignment": "left",
    "background": "dark",
    "content_width": null,
    "media": {
      "desktop": {
        "width": { "value": null, "unit": null },
        "height": { "value": null, "unit": null },
        "fit": "normal"
      },
      "mobile": {
        "width": { "value": null, "unit": null },
        "height": { "value": null, "unit": null },
        "fit": "normal"
      }
    }
  }
}
```

`CTADataNormalizer` is the compatibility boundary for legacy flat JSON. Rendering and editor hydration consume this shape, and the shared save manager persists it only when a record is saved. Opening an editor never writes to the database.

CTA actions support `url` and `form`. Legacy `button_url`, `secondary_button_url`, and earlier v2 nested `url` values normalize to URL actions. Form actions reference reusable Forms by ID and never create Leads directly.

Form actions may set `display` to `page` or `modal`. The action value is authoritative, allowing one Form to open differently from different CTAs. Existing Form actions without `display` continue using the Form's `display_mode` as a backward-compatible fallback.

CTA provides attribution in a POST body to the Form boundary. Page actions are redirected to the clean `/forms/{slug}` URL after the context is stored in session; modal actions POST to the lazy fragment endpoint. Attribution is never appended to generated public URLs.
