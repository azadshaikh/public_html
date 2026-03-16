# Default Theme for Astero (Laravel)

A clean, modern, and responsive theme designed for seamless integration with Astero and Laravel. Perfect for blogs, business sites, and portfolios.

## Features

- Responsive design
- Dark mode support
- Custom colors and fonts
- Sidebar and widgets
- Modular blocks, sections, and layouts
- Easy customization via JSON config

## Directory Structure

```
themes/default/
├── assets/         # CSS, JS, images
├── blocks/         # Reusable content blocks
├── config/         # Theme configuration files
│   ├── options.json
│   └── config.json
├── layouts/        # Page layouts
├── manifest.json   # Theme metadata
├── screenshot.png  # Theme preview image
├── sections/       # Page sections
└── templates/      # Page templates
```

## Configuration

- `manifest.json`: Theme metadata and asset references
- `config/config.json`: Default options and customizer settings
- `config/options.json`: User-defined or runtime options

## How to Use

1. Copy the `default` theme folder into your `themes/` directory.
2. Activate the theme via your application's theme management UI or configuration.
3. Customize options in `config/options.json` or through your app's UI.
4. Add or modify blocks, sections, and layouts as needed.

## Customization

- Update colors, fonts, and layout via the customizer or by editing config files.
- Add new blocks or sections by creating files in the respective folders.
- All user-facing strings should be localized for multi-language support.

## Author

- **Azad Shaikh**  
  [https://astero.com](https://astero.com)

## License

MIT

---

For more information, see the main project documentation or contact the author.
