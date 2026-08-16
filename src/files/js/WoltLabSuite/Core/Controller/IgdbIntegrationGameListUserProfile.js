/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/Ui/Notification", "WoltLabSuite/Core/User"], function (require, exports, tslib_1, Ajax_1, IgdbIntegrationGameDialog_1, Dialog_1, Language_1, Notification_1, User_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.init = init;
    Dialog_1 = tslib_1.__importDefault(Dialog_1);
    User_1 = tslib_1.__importDefault(User_1);
    function updateGameCount(gameCount) {
        const countElement = document.getElementById('igdbIntegrationGameCount');
        if (countElement !== null) {
            countElement.textContent = (0, Language_1.getPhrase)('wcf.user.option.igdb_integration_game_count') + ': ' + gameCount;
        }
    }
    async function quickRemoveGame(gameId, userId) {
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
                        playersElement.style.display = returnValues.playerCount <= 0 ? 'none' : '';
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
    }
});
