/**
 * Provides the script features for the game list page on user profiles.
 *
 * @author		Berny23
 * @copyright	2023 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameListUserProfile
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language"], function (require, exports, tslib_1, Dialog_1, Language) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.init = void 0;
    Dialog_1 = tslib_1.__importDefault(Dialog_1);
    Language = tslib_1.__importStar(Language);
    function init(gameId, userId) {
        var _a;
        var gameUserEditDialog = new Dialog_1.default('gameUserEditDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGameUserEditDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
                userId: userId
            },
            dialog: {
                title: Language.get('wcf.igdb_integration.dialog.game_user_edit_title')
            },
            submitActionName: 'submitGameUserEditDialog',
            successCallback(returnValues) {
                var _a;
                if (returnValues.playerCount <= 0) {
                    // Remove game from profile list
                    (_a = document.getElementById('gameBox' + returnValues.gameId)) === null || _a === void 0 ? void 0 : _a.remove();
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
                            ratingElement.innerHTML += '<span class="icon icon16 fa-star orange"></span>';
                        }
                        if (returnValues.isOwned) {
                            playersElement.classList.add('isOwned');
                        }
                        else {
                            playersElement.classList.remove('isOwned');
                        }
                        var html = '<p class="gameOwnRating">';
                        for (let i = 0; i < returnValues.ownRating; i++) {
                            html += '<span class="icon icon16 fa-star orange"></span>';
                        }
                        html += '</p><p class="gamePlayerCount pointer';
                        if (returnValues.isOwned) {
                            html += ' isOwned';
                        }
                        html += '" id="gamePlayerCount' + returnValues.gameId + '"></p>';
                        let gameUserInfoElement = document.querySelector('#gameBox' + returnValues.gameId + ' .gameUserInfo');
                        if (gameUserInfoElement !== null) {
                            gameUserInfoElement.innerHTML = html;
                        }
                    }
                }
            }
        });
        (_a = document.getElementById('gameOverlay' + gameId)) === null || _a === void 0 ? void 0 : _a.addEventListener('click', function () {
            gameUserEditDialog.open();
        });
    }
    exports.init = init;
});
