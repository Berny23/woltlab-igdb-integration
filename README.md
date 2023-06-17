# IGDB Integration for WoltLab Suite

<a href="https://www.buymeacoffee.com/Berny23" title="Donate to this project using Buy Me A Coffee"><img src="https://img.shields.io/badge/buy%20me%20a%20coffee-donate-yellow.svg" alt="Buy Me A Coffee donate button" /></a>

Allows you to automatically import and manage all games from IGDB within the WoltLab Suite front-end.

## Features

- Automatically imports all games from search results
- Users can add games to their library
- Users can rate games and see the average ratings
- Players appear in a toplist, size configurable
- Modern and user-friendly design
- English and German interface
- Supports automated Trophies
- Supports system-wide image proxy for privacy
- Supports global options, user settings and permissions
- Shows owned games and game count on profile pages
- Users can view a list of all players of a game
- Shows game titles in the current user's language, if available
- Sortable by name, year, players and rating
- Search allows for any part of the title to be in any order
- Compatible with light and dark Styles

## Demo

![image](https://github.com/Berny23/woltlab-igdb-integration/assets/36038743/27ef300c-e1f9-43b3-b68d-66218108ca13)

![image](https://github.com/Berny23/woltlab-igdb-integration/assets/36038743/12dc4c11-fe9c-4763-a194-5d75e07dbfe4)

## Download

https://www.woltlab.com/pluginstore/file/7473-igdb-integration/

## Tutorial

To be able to access the IGDB API, you have to follow this short guide:
1. Log in or sign up on Twitch: https://dev.twitch.tv/login
2. Enable Two-Factor Authentication if you haven't already: https://www.twitch.tv/settings/security
3. Register a new application here (Name: Your forum name, OAuth Redirect URL: Your forum address): https://dev.twitch.tv/console/apps/create
4. Click on Manage next to your created application: https://dev.twitch.tv/console/apps
5. Click on "New Secret".
6. Paste your Client ID and Client Secret in the appropriate fields in the IGDB Integration settings inside your WoltLab Suite.

![image](https://github.com/Berny23/woltlab-igdb-integration/assets/36038743/d3a4b332-2d63-4117-a2be-3b743f381406)

## Building

1. (optional) Run ``npm install`` in **/src** and generate a JavaScript (.js) file from every TypeScript (.ts) file with ``tsc build``
2. On Windows, run build.bat in **/tools**
3. The installable package will be created in **/build**
4. Install via WoltLab Suite package manager

## Privacy notice

This plugin sends all API requests to igdb.com only through the web server, not in the user's browser. This means that no user data is transmitted, except for the following:

- Search terms

For external cover images, the system-wide Image Proxy is used so that no user data is forwarded. If it is not enabled, the images will be loaded directly in the user's browser from igdb.com, sending any associated user data.

## Feature requests & bug reports

You can either create a **GitHub issue** in this repository (in German/English) or create a new post in the **[IGDB Integration support area on CompiWare](https://www.compiware-forum.de/forum/board/232-igdb-integration-f%C3%BCr-woltlab-suite/)** (in German only).

## Acknowledgements

- The active members of the [CompiWare](https://www.compiware-forum.de/) forum for their support and ideas.
