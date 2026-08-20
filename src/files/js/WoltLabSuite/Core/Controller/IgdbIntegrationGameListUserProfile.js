/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog", "WoltLabSuite/Core/Controller/IgdbIntegrationGameList", "WoltLabSuite/Core/Event/Handler", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/Ui/Notification", "WoltLabSuite/Core/User"], function (require, exports, tslib_1, Ajax_1, IgdbIntegrationGameDialog_1, IgdbIntegrationGameList_1, EventHandler, Dialog_1, Language_1, Notification_1, User_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.watchTabSelection = watchTabSelection;
    exports.watchFilterBoxVisibility = watchFilterBoxVisibility;
    exports.init = init;
    EventHandler = tslib_1.__importStar(EventHandler);
    Dialog_1 = tslib_1.__importDefault(Dialog_1);
    User_1 = tslib_1.__importDefault(User_1);
    const FILTER_PARAMETER_REGEX = /[?&](?:gameSearch|gameSortField|gameSortOrder|gamePlatforms(?:\[\]|%5B%5D)|pageNo)=[^&#]*/g;
    const GAME_LIST_TAB_NAME = 'igdb_integration_game_list';
    let tabWatcherActive = false;
    let removedFilterParameters = '';
    /**
     * Removes the game list filter parameters from the address bar when another
     * profile tab is selected, so the filter is not reapplied on reload there,
     * and restores them when the games tab is selected again.
     */
    function watchTabSelection() {
        if (tabWatcherActive) {
            return;
        }
        tabWatcherActive = true;
        EventHandler.add('com.woltlab.wcf.simpleTabMenu_profileContent', 'select', (data) => {
            // The profile URL is not a regular query string without URL rewriting
            // (e.g. "index.php?user/1-example/&gameSearch="), so the parameters
            // are handled on the raw URL instead of using URLSearchParams
            const hash = window.location.hash;
            let url = window.location.href.replace(/#.*$/, '');
            if (data.activeName === GAME_LIST_TAB_NAME) {
                // Restore the parameters that were removed when the tab was left,
                // the displayed list still matches them
                if (removedFilterParameters !== '') {
                    url += (url.includes('?') ? '&' : '?') + removedFilterParameters;
                    removedFilterParameters = '';
                    window.history.replaceState(undefined, '', url + hash);
                }
                return;
            }
            const parameters = url.match(FILTER_PARAMETER_REGEX);
            if (parameters === null) {
                return;
            }
            removedFilterParameters = parameters.map((parameter) => parameter.substring(1)).join('&');
            url = url.replace(FILTER_PARAMETER_REGEX, '');
            if (!url.includes('?') && url.includes('&')) {
                // The removed part contained the "?" of the remaining parameters
                url = url.replace('&', '?');
            }
            window.history.replaceState(undefined, '', url + hash);
        });
    }
    /**
     * Only shows the sidebar filter box while the games tab is selected.
     */
    function watchFilterBoxVisibility() {
        const filterBox = document.getElementById('igdbIntegrationGameListFilterBox');
        if (filterBox === null) {
            return;
        }
        // The server only preselects the games tab when filter parameters are
        // present; a tab named in the URL hash is activated by the tab menu script
        // alone, so the initial visibility has to be corrected here
        const hashMatch = /^#+([^/]+)/.exec(window.location.hash);
        if (hashMatch !== null) {
            const tabContent = document.getElementById(hashMatch[1]);
            if (tabContent !== null && tabContent.parentElement?.id === 'profileContent') {
                filterBox.hidden = hashMatch[1] !== GAME_LIST_TAB_NAME;
            }
        }
        EventHandler.add('com.woltlab.wcf.simpleTabMenu_profileContent', 'select', (data) => {
            filterBox.hidden = data.activeName !== GAME_LIST_TAB_NAME;
        });
    }
    function updateGameCount(gameCount) {
        const countElement = document.getElementById('igdbIntegrationGameCount');
        if (countElement !== null) {
            countElement.textContent = (0, Language_1.getPhrase)('wcf.user.option.igdb_integration_game_count') + ': ' + gameCount;
        }
    }
    async function quickRemoveGame(gameId, userId) {
        if (!(await (0, IgdbIntegrationGameDialog_1.confirmGameRemoval)((0, IgdbIntegrationGameDialog_1.getGameDialogTitle)(gameId)))) {
            return;
        }
        const returnValues = await (0, Ajax_1.dboAction)('quickRemoveGame', 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
            .payload({
            gameId: gameId,
            userId: userId,
        })
            .dispatch();
        // Remove game from profile list and update the owned games count
        document.getElementById('gameBox' + gameId)?.remove();
        updateGameCount(returnValues.gameCount);
        (0, Notification_1.show)();
    }
    function init(gameId, userId) {
        var gameUserEditDialog = new Dialog_1.default('gameUserEditDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGameUserEditDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
                userId: userId
            },
            dialog: {
                title: (0, IgdbIntegrationGameDialog_1.getGameDialogTitle)(gameId) || (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_user_edit_title'),
                onShow: (content) => (0, IgdbIntegrationGameDialog_1.initGameUserEditDialogEvents)(content)
            },
            submitActionName: 'submitGameUserEditDialog',
            successCallback(rawReturnValues) {
                const returnValues = rawReturnValues;
                // The returned game count belongs to the current user, so only
                // update the counter when viewing the own profile
                if (userId === User_1.default.userId) {
                    updateGameCount(returnValues.gameCount);
                }
                if (returnValues.playerCount <= 0) {
                    // Remove game from profile list
                    document.getElementById('gameBox' + returnValues.gameId)?.remove();
                }
                else {
                    // Insert returned values into page
                    var ratingElement = document.querySelector('#gameBox' + returnValues.gameId +
                        ' .gameOwnRating');
                    var playersElement = document.getElementById('gamePlayerCount' + returnValues.gameId);
                    if (ratingElement !== null && playersElement !== null) {
                        ratingElement.innerHTML = '';
                        playersElement.innerHTML = '';
                        playersElement.style.display = returnValues.playerCount <= 0 ? 'none' : '';
                        const userIcon = document.createElement('fa-icon');
                        userIcon.size = 16;
                        userIcon.setIcon('user', true);
                        playersElement.appendChild(userIcon);
                        playersElement.innerHTML += ' ' + returnValues.playerCount;
                        for (let i = 0; i < returnValues.ownRating; i++) {
                            // Add star icon
                            const starIcon = document.createElement('fa-icon');
                            starIcon.size = 16;
                            starIcon.setIcon('star', true);
                            ratingElement.appendChild(starIcon);
                        }
                        if (returnValues.isOwned) {
                            playersElement.classList.add('isOwned');
                        }
                        else {
                            playersElement.classList.remove('isOwned');
                        }
                    }
                }
            }
        });
        document.getElementById('gameOverlayEdit' + gameId)?.addEventListener('click', function () {
            gameUserEditDialog.open();
        });
        document.getElementById('gameOverlayQuickRemove' + gameId)?.addEventListener('click', function () {
            void quickRemoveGame(gameId, userId);
        });
        document.getElementById('gamePlayerCount' + gameId)?.addEventListener('click', function () {
            void (0, IgdbIntegrationGameList_1.showGamePlayerListDialog)(gameId);
        });
    }
});
