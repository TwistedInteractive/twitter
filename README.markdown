# Twitter

- Version: 0.1
- Author: Simon de Turck
- Build Date: 11-4-2011
- Requirements: Symphony 2.*

## Description

Fairly blunt filter to protect Symphony events from common cross-site scripting (XSS) attacks.

## Installation

1. Place the `twitter` folder in your Symphony `extensions` directory.
2. Go to _System > Extensions_, select "Twitter", choose "Enable" from the with-selected menu, then click Apply.
3. Go to _System > Preferences_, and connect the extension to your twitter account.

## Usage of the autotweet field

- Add the autotweet field to your section
- Enter the default message, use xpath to construct links
- After saving a new entry you can tweet from either the edit page or the list overview

## Usage if the library

Import the lib/twitter.php into your edatasources, extensions or whereever. Use the tokens in the config to connect to twitter

## Notes

This extension is still in early development.

This extension uses some code by Rowan Lewis and Tijs Verkoyen... see the Licence.txt