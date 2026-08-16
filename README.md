# IGDB Integration for WoltLab Suite

<a href="https://www.buymeacoffee.com/Berny23" title="Donate to this project using Buy Me A Coffee"><img src="https://img.shields.io/badge/buy%20me%20a%20coffee-donate-yellow.svg" alt="Buy Me A Coffee donate button" /></a>

Allows you to automatically import and manage all games from IGDB within the WoltLab Suite front-end.

## Features

- Automatically imports all games from search results
- Users can easily add, rate, and remove games
- Shows average ratings and a configurable player toplist
- Optional region-specific covers (Europe, Japan, Korea)
- Filterable by platforms, sortable by name, year, players, rating and last activity
- Search allows for any part of the title to be in any order
- Shows game titles in the current user's language, if available
- Shows owned games and game count on profile pages
- Users can view a list of all players of a game
- Recent activity events for adding, removing and rating games
- Supports automated Trophies and activity points for owned games
- Modern admin game list with filters, bulk actions and manual game creation
- Supports global options, user settings and permissions
- Supports system-wide image proxy for privacy
- English and German interface
- Compatible with light and dark Styles
- Branding can be disabled for free

## Demo

![image](https://github.com/Berny23/woltlab-igdb-integration/assets/36038743/4e23a901-a5bc-4a15-aa3e-f36e2d29949a)

![image](https://github.com/Berny23/woltlab-igdb-integration/assets/36038743/bf62b156-6cce-4cae-8d8b-9f2ce95d1461)


Notice: As an example, the paid third-party style “[Nubia](https://www.woltlab.com/pluginstore/file/6705-nubia/)” was used for these screenshots, which is not related to this project.

## Download

https://www.woltlab.com/pluginstore/file/7473-igdb-integration/

## Initial setup

In order for the plugin to access the IGDB API, you only need to follow these short instructions:
1. Log in or sign up on Twitch: https://dev.twitch.tv/login
2. Enable Two-Factor Authentication if you haven't already: https://www.twitch.tv/settings/security
3. Register a new application here (Name: Your forum name, OAuth Redirect URL: Your forum address): https://dev.twitch.tv/console/apps/create
4. Click on Manage next to your created application: https://dev.twitch.tv/console/apps
5. Click on “New Secret”.
6. Paste your Client ID and Client Secret in the appropriate fields in the IGDB Integration settings inside your WoltLab Suite.

![image](https://github.com/Berny23/woltlab-igdb-integration/assets/36038743/d3a4b332-2d63-4117-a2be-3b743f381406)

## Building

1. On Linux, run build.sh in **/tools** (build.bat on Windows) to install dependencies, compile TypeScript to JavaScript and create the package
2. The installable package will be in **/dist**
3. Install via WoltLab Suite package manager

## Development environment

A Podman compose stack (nginx, PHP, MariaDB) is included in **/dev**. Requires `podman-compose` and a downloaded zip file of **[WoltLab Suite Core](https://www.woltlab.com/en/woltlab-suite-download/)**.

One-time preparation:
```sh
# Allow rootless Podman using port 80 and above
echo 'net.ipv4.ip_unprivileged_port_start=80' | sudo tee /etc/sysctl.d/50-unprivileged-ports.conf
sudo sysctl --system
# Make script executable
chmod +x ./setup-woltlab.sh
```

Build and start environment:
```sh
cd dev
podman compose up -d --build
./setup-woltlab.sh ~/Downloads/woltlab-suite-*.zip
```

Then open http://localhost/install.php (database: host `db`, user/password/database `woltlab`). WoltLab files and the database are stored in named volumes.
Use `podman compose down` to shut everything down.

### Plugin reload via developer tools (Projects)

The plugin's **/src** directory is mounted read-only into the php container at `/var/www/plugin-src`.

1. In the ACP, open **Configuration → Modules → Development** and turn on **Enable developer tools**.
2. Open **Configuration → Developer Tools → Projects** and add a project with path `/var/www/plugin-src`.
3. If the plugin is not installed yet, install it from the project list. Otherwise, open the project's **Sync** tab and synchronize all files.

Note: TypeScript is not compiled automatically, run `tsc` in **/src** first if you changed anything in **/src/ts**.

## Privacy notice

This plugin sends all API requests to igdb.com only through the web server, not in the user's browser. This means that no user data is transmitted, except for the following:

- Search terms

For external cover images, the system-wide Image Proxy is used so that no user data is forwarded. If it is not enabled, the images will be loaded directly in the user's browser from igdb.com, sending any associated user data.

## Feature requests & bug reports

You can either create a **GitHub issue** in this repository (in German/English) or create a new post in the **[IGDB Integration support area on CompiWare](https://www.compiware-forum.de/forum/board/232-igdb-integration-f%C3%BCr-woltlab-suite/)** (in German only).

## Acknowledgements

- The active members of the [CompiWare](https://www.compiware-forum.de/) forum for their support and ideas.
