/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/Ui/Notification", "WoltLabSuite/Core/User"], function (require, exports, tslib_1, Ajax_1, Dialog_1, Language_1, Notification_1, User_1) {
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
    function getGameDialogTitle(gameId) {
        // Use the game name and release year as the dialog title: "NAME (YEAR)"
        const gameName = document.querySelector('#gameBox' + gameId + ' .gameInfo > h3')?.textContent?.trim();
        if (!gameName) {
            return '';
        }
        const releaseYear = document.querySelector('#gameBox' + gameId + ' .gameInfo > small')?.textContent?.trim();
        return releaseYear ? gameName + ' (' + releaseYear + ')' : gameName;
    }
    function initGameUserEditDialogEvents(content) {
        const ownedYes = content.querySelector('#isOwned');
        const ownedNo = content.querySelector('#isOwned_no');
        const ratingContainer = content.querySelector('#ratingContainer');
        if (ownedYes === null || ownedNo === null || ratingContainer === null || content.dataset.igdbEventsBound === '1') {
            return;
        }
        content.dataset.igdbEventsBound = '1';
        // Enable the owned toggle when a rating is selected
        ratingContainer.querySelectorAll('.ratingList > li:not(.ratingMetaButton)').forEach((listItem) => {
            listItem.addEventListener('click', function () {
                ownedYes.checked = true;
            });
        });
        // Reset the rating when the owned toggle is turned off
        ownedNo.addEventListener('change', function () {
            if (!ownedNo.checked) {
                return;
            }
            const ratingInput = content.querySelector('#rating');
            if (ratingInput !== null) {
                ratingInput.value = '0';
            }
            ratingContainer.querySelectorAll('.ratingList > li:not(.ratingMetaButton)').forEach((listItem) => {
                listItem.querySelector('fa-icon')?.setIcon('star', false);
            });
        });
    }
    function init(gameId, userId) {
        var gameUserEditDialog = new Dialog_1.default('gameUserEditDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGameUserEditDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
                userId: userId
            },
            dialog: {
                title: getGameDialogTitle(gameId) || (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_user_edit_title'),
                onShow: (content) => initGameUserEditDialogEvents(content)
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
