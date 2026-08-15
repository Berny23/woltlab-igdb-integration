/**
 * Provides the script features for the game list page.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameList
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/Ui/Notification"], function (require, exports, tslib_1, Dialog_1, Language_1, Notification_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.init = init;
    Dialog_1 = tslib_1.__importDefault(Dialog_1);
    async function showGameUserEditDialog(gameId) {
        // Call dialog form 
        let form = new Dialog_1.default('gameUserEditDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGameUserEditDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
            },
            dialog: {
                title: (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_user_edit_title')
            },
            submitActionName: 'submitGameUserEditDialog',
            successCallback(returnValues) {
                const response = returnValues;
                // Insert returned values into page
                var ratingElement = document.querySelector('#gameBox' + response.gameId + ' .gameAverageRating');
                var playersElement = document.getElementById('gamePlayerCount' + response.gameId);
                if (ratingElement !== null && playersElement !== null) {
                    ratingElement.innerHTML = '';
                    playersElement.innerHTML = '';
                    playersElement.style.display = response.playerCount <= 0 ? 'none' : '';
                    // Add user icon
                    const userIcon = document.createElement('fa-icon');
                    userIcon.size = 16;
                    userIcon.setIcon('user', true);
                    playersElement.appendChild(userIcon);
                    playersElement.innerHTML += ' ' + response.playerCount;
                    for (let i = 0; i < response.averageRating; i++) {
                        // Add star icon
                        const starIcon = document.createElement('fa-icon');
                        starIcon.size = 16;
                        starIcon.setIcon('star', true);
                        ratingElement.appendChild(starIcon);
                    }
                    if (response.isOwned) {
                        playersElement.classList.add('isOwned');
                    }
                    else {
                        playersElement.classList.remove('isOwned');
                    }
                }
                (0, Notification_1.show)();
            }
        });
        form.open();
    }
    async function showGamePlayerListDialog(gameId) {
        let form = new Dialog_1.default('gamePlayerListDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGamePlayerListDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
            },
            dialog: {
                title: (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_player_list_title')
            }
        });
        form.open();
    }
    function init(gameId) {
        document.getElementById('gameOverlay' + gameId)?.addEventListener('click', function () {
            showGameUserEditDialog(gameId);
        });
        document.getElementById('gamePlayerCount' + gameId)?.addEventListener('click', function () {
            showGamePlayerListDialog(gameId);
        });
    }
});
