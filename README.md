# Trello Export

Export all comments from Trello.

Trello's built-in JSON exports are limited to 1000 actions, so for large boards, aren't useful. This app exports all comments into a TSV (CSV but tabs) file for you.

## Usage

Download this repo, and run `composer install`. Then, to configure:

1. Create [an API key for Trello](https://trello.com/app-key)
2. Select "manually generate a Token"
3. Copy `config.sample.php` to `config.php` and set your key and token.
4. Set `id` to the board ID (copy from the Trello URL)
5. Run `export.php`

## License

Licensed under the [ISC License](LICENSE). Copyright 2020 Human Made.
