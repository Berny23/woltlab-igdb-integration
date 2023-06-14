/**
 * Provides the script features for the game list page.
 *
 * @author		Berny23
 * @copyright	2023 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameList
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language"], function (require, exports, tslib_1, Dialog_1, Language) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.init = void 0;
    Dialog_1 = tslib_1.__importDefault(Dialog_1);
    Language = tslib_1.__importStar(Language);
    function init(gameId) {
        var _a, _b;
        var gameUserEditDialog = new Dialog_1.default('gameUserEditDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGameUserEditDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
            },
            dialog: {
                title: Language.get('wcf.igdb_integration.dialog.game_user_edit_title')
            },
            submitActionName: 'submitGameUserEditDialog',
            successCallback(returnValues) {
                // Insert returned values into page
                var ratingElement = document.querySelector('#gameBox' + returnValues.gameId + ' .gameAverageRating');
                var playersElement = document.getElementById('gamePlayerCount' + returnValues.gameId);
                if (ratingElement !== null && playersElement !== null) {
                    ratingElement.innerHTML = '';
                    playersElement.style.display = returnValues.playerCount <= 0 ? 'none' : '';
                    playersElement.innerHTML = '<span class="icon fa-user"></span> ' +
                        returnValues.playerCount;
                    for (let i = 0; i < returnValues.averageRating; i++) {
                        ratingElement.innerHTML += '<span class="icon icon16 fa-star orange"></span>';
                    }
                    if (returnValues.isOwned) {
                        playersElement.classList.add('isOwned');
                    }
                    else {
                        playersElement.classList.remove('isOwned');
                    }
                }
            }
        });
        (_a = document.getElementById('gameOverlay' + gameId)) === null || _a === void 0 ? void 0 : _a.addEventListener('click', function () {
            gameUserEditDialog.open();
        });
        var gamePlayerListDialog = new Dialog_1.default('gamePlayerListDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGamePlayerListDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
            },
            dialog: {
                title: Language.get('wcf.igdb_integration.dialog.game_player_list_title')
            }
        });
        (_b = document.getElementById('gamePlayerCount' + gameId)) === null || _b === void 0 ? void 0 : _b.addEventListener('click', function () {
            gamePlayerListDialog.open();
        });
    }
    exports.init = init;
});
