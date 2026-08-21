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
    exports.updateQuickToggleButton = updateQuickToggleButton;
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
    /**
     * Toggles the quick add button of a game box between adding and removing.
     */
    function updateQuickToggleButton(gameId, isOwned) {
        const quickAddButton = document.getElementById('gameOverlayQuickAdd' + gameId);
        if (quickAddButton === null) {
            return;
        }
        quickAddButton.dataset.isOwned = isOwned ? '1' : '0';
        quickAddButton.title = (0, Language_1.getPhrase)(isOwned
            ? 'wcf.igdb_integration.page.game_quick_remove'
            : 'wcf.igdb_integration.page.game_quick_add');
        quickAddButton.querySelector('fa-icon')?.setIcon(isOwned ? 'minus' : 'plus', true);
    }
    /**
     * Keeps the dialog centered together with its cover panel. On wide screens,
     * the cover is positioned left of the dialog container and spans its full
     * height, so its width depends on the dialog height and the dialog has to be
     * shifted right by half of it. The width is passed to the stylesheet as a
     * custom property.
     */
    function initGameDialogCover(content) {
        const cover = content.querySelector('.igdbIntegrationGameDialogCover');
        const container = content.closest('.dialogContainer');
        if (cover === null || container === null || cover.dataset.igdbObserved === '1') {
            return;
        }
        cover.dataset.igdbObserved = '1';
        container.classList.add('igdbIntegrationGameDialogContainer');
        const updateOffset = () => {
            // The panel is taken out of the flow only on wide screens, in the
            // stacked layout the dialog stays centered as usual
            const isSidePanel = getComputedStyle(cover).position === 'absolute';
            container.style.setProperty('--igdbCoverWidth', isSidePanel ? cover.offsetWidth + 'px' : '0px');
        };
        updateOffset();
        const observer = new ResizeObserver(() => {
            if (!container.isConnected) {
                observer.disconnect();
                return;
            }
            updateOffset();
        });
        observer.observe(cover);
        observer.observe(container);
    }
    function initGameUserEditDialogEvents(content) {
        initGameDialogCover(content);
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
