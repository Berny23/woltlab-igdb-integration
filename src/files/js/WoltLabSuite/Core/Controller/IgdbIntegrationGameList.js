/**
 * Provides the script features for the game list page.
 *
 * @author		Berny23
 * @copyright	2023 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameList
 */
define(["require", "exports", "WoltLabSuite/Core/Component/Dialog", "WoltLabSuite/Core/Ui/Notification"], function (require, exports, Dialog_1, Notification_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.init = void 0;
    async function showGameUserEditDialog(url) {
        const { ok, result } = await (0, Dialog_1.dialogFactory)().usingFormBuilder().fromEndpoint(url);
        if (ok) {
            // Insert returned values into page
            var ratingElement = document.querySelector('#gameBox' + result.gameId + ' .gameAverageRating');
            var playersElement = document.getElementById('gamePlayerCount' + result.gameId);
            if (ratingElement !== null && playersElement !== null) {
                ratingElement.innerHTML = '';
                playersElement.innerHTML = '';
                playersElement.style.display = result.playerCount <= 0 ? 'none' : '';
                // Add user icon
                const userIcon = document.createElement('fa-icon');
                userIcon.size = 16;
                userIcon.setIcon('user', true);
                playersElement.appendChild(userIcon);
                playersElement.innerHTML += ' ' + result.playerCount;
                for (let i = 0; i < result.averageRating; i++) {
                    // Add star icon
                    const starIcon = document.createElement('fa-icon');
                    starIcon.size = 16;
                    starIcon.setIcon('star', true);
                    ratingElement.appendChild(starIcon);
                }
                if (result.isOwned) {
                    playersElement.classList.add('isOwned');
                }
                else {
                    playersElement.classList.remove('isOwned');
                }
            }
            (0, Notification_1.show)();
        }
    }
    async function showGamePlayerListDialog(url) {
        const { ok, result } = await (0, Dialog_1.dialogFactory)().usingFormBuilder().fromEndpoint(url);
        if (ok) {
            (0, Notification_1.show)();
        }
    }
    function init(gameId, gameOverlayElement, gamePlayerCountElement) {
        gameOverlayElement.addEventListener('click', function () {
            void showGameUserEditDialog(gameOverlayElement.dataset.url);
        });
        gamePlayerCountElement.addEventListener('click', function () {
            void showGamePlayerListDialog(gamePlayerCountElement.dataset.url);
        });
    }
    exports.init = init;
});
