/**
 * Provides the script features shared between the game list and the user
 * profile game list.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog
 */
define(["require", "exports", "WoltLabSuite/Core/Component/Confirmation", "WoltLabSuite/Core/Language"], function (require, exports, Confirmation_1, Language_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.getGameDialogTitle = getGameDialogTitle;
    exports.confirmGameRemoval = confirmGameRemoval;
    exports.initGameUserEditDialogEvents = initGameUserEditDialogEvents;
    function getGameDialogTitle(gameId) {
        // Use the game name and release year as the dialog title: "NAME (YEAR)"
        const gameName = document.querySelector('#gameBox' + gameId + ' .gameInfo > h3')?.textContent?.trim();
        if (!gameName) {
            return '';
        }
        const releaseYear = document.querySelector('#gameBox' + gameId + ' .gameInfo > small')?.textContent?.trim();
        return releaseYear ? gameName + ' (' + releaseYear + ')' : gameName;
    }
    /**
     * Asks the user to confirm that the game is removed.
     */
    async function confirmGameRemoval(gameTitle) {
        const question = gameTitle
            ? (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_quick_remove_confirm', { gameTitle: gameTitle })
            : (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_quick_remove_confirm_indeterminate');
        return (0, Confirmation_1.confirmationFactory)().custom(question).withoutMessage();
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
});
