{{--
    The few rules that separate "unstyled" from "broken", for the package's own document only: a
    site with a layout of its own styles the catalogue itself. A grid that is a grid, a filter
    beside it on a wide screen and above it on a phone, a value with nothing behind it drawn grey.
--}}
<style>
    * { box-sizing: border-box; }
    body { margin: 0; padding: 0 16px; font: 16px/1.5 system-ui, sans-serif; color: #1f2328; background: #fff; }
    img { max-width: 100%; height: auto; display: block; }
    .webx-catalog-listing { display: grid; gap: 24px; grid-template-columns: minmax(0, 1fr); }
    @media (min-width: 800px) { .webx-catalog-listing { grid-template-columns: 240px minmax(0, 1fr); } }
    .webx-catalog-grid { list-style: none; margin: 0; padding: 0; display: grid; gap: 16px; grid-template-columns: repeat(auto-fill, minmax(180px, 1fr)); }
    .webx-catalog-filter ul { list-style: none; margin: 0; padding: 0; }
    .webx-catalog-filter ul ul { padding-left: 16px; }
    .webx-catalog-filter__option.is-disabled { color: #8c959f; }
    .webx-catalog-filter__option.is-selected { font-weight: 600; }
    .webx-catalog-filter fieldset { border: 0; margin: 0 0 16px; padding: 0; }
    .webx-catalog-sort, .webx-catalog-pagination { display: flex; flex-wrap: wrap; gap: 12px; }
    .webx-catalog-sort .is-selected, .webx-catalog-pagination .is-current { font-weight: 600; }
</style>
