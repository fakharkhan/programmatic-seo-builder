# Programmatic SEO Builder

A powerful WordPress plugin for automatically generating SEO-optimized pages using templates and dynamic content replacement.

## Features

### 1. Single Page Generator
- Select any existing page as a template
- Replace content dynamically using find & replace
- Add multiple find & replace pairs
- Preview template before generation
- Generated pages are saved as drafts for review

### 2. Bulk CSV Page Generator
- Generate multiple pages from a single CSV file
- Simple CSV format:
  - First row: Text to find (e.g., [keyword], [location])
  - Following rows: Replacement values
- Real-time CSV preview
- Progress tracking during generation
- All pages are saved as drafts

### 3. Settings & Configuration
- DeepSeek API integration for content enhancement
- Test API connection functionality
- Common content definitions for consistent replacements
- Secure API key storage

## Usage

### Single Page Generation
1. Select a template page
2. Enter primary keyword find/replace values
3. Add additional find/replace pairs as needed
4. Generate the page

### CSV Bulk Generation
1. Select a template page
2. Upload a CSV file formatted as:
    [keyword],[location]
    Web Designer,New York
    Web Developer,Los Angeles
    UI Designer,Chicago
3. Review the CSV preview
4. Click "Generate Pages" to create all pages

## Installation

1. Upload the plugin files to `/wp-content/plugins/programmatic-seo-builder`
2. Activate the plugin through the 'Plugins' menu in WordPress
3. Configure your DeepSeek API key in the Settings tab
4. Start generating pages

## Requirements

- WordPress 5.0 or higher
- PHP 7.2 or higher
- DeepSeek API key for content enhancement

## Security

- All generated pages are saved as drafts for review
- API keys are stored securely in WordPress options
- Nonce verification for all AJAX requests
- User capability checks for all actions

## Support

For support, please visit the [GitHub repository](https://github.com/fakharkhan/programmatic-seo-builder) or contact the plugin authors.

## Authors

- Fakhar Zaman Khan
- Hasan Zaheer
- Mustafa Najoom

## License

GPL v2 or later
