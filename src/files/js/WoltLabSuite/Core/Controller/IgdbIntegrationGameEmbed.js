/**
 * Provides the buttons on game boxes embedded into messages via the
 * [igdbgame] bbcode: opening the game details dialog and quickly adding or
 * removing the game.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameEmbed
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Controller/IgdbIntegrationGameDialog", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/Ui/Notification"], function (require, exports, tslib_1, Ajax_1, IgdbIntegrationGameDialog_1, Dialog_1, Language_1, Notification_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.setup = setup;
    Dialog_1 = tslib_1.__importDefault(Dialog_1);
    let initialized = false;
    function updateEmbeds(response) {
        // A game can be embedded multiple times on the same page, e.g. in
        // several posts of a thread, so update all of its embed boxes
        document.querySelectorAll('.igdbIntegrationGameEmbed[data-game-id="' + response.gameId + '"]').forEach((embed) => {
            const quickAddButton = embed.querySelector('.igdbIntegrationGameEmbedQuickAdd');
            if (quickAddButton !== null) {
                quickAddButton.dataset.isOwned = response.isOwned ? '1' : '0';
                quickAddButton.title = (0, Language_1.getPhrase)(response.isOwned
                    ? 'wcf.igdb_integration.page.game_quick_remove'
                    : 'wcf.igdb_integration.page.game_quick_add');
                quickAddButton.querySelector('fa-icon')?.setIcon(response.isOwned ? 'minus' : 'plus', true);
            }
            const playerCountElement = embed.querySelector('.igdbIntegrationGameEmbedPlayerCount');
            if (playerCountElement !== null && response.playerCount >= 0) {
                playerCountElement.textContent = String(response.playerCount);
            }
            // The author badge and rating reflect the message author; they can
            // only change through this page if the viewer is the author
            if (embed.dataset.authorIsCurrentUser === '1') {
                embed.classList.toggle('igdbIntegrationGameEmbedAuthorOwns', response.isOwned);
                updateAuthorRating(embed, response);
            }
        });
    }
    function updateAuthorRating(embed, response) {
        const rating = response.isOwned ? (response.currentUserRating ?? 0) : 0;
        let ratingElement = embed.querySelector('.igdbIntegrationGameEmbedAuthorRating');
        if (rating <= 0) {
            ratingElement?.remove();
            return;
        }
        if (ratingElement === null) {
            ratingElement = document.createElement('p');
            ratingElement.className = 'igdbIntegrationGameEmbedAuthorRating orange';
            ratingElement.title = (0, Language_1.getPhrase)('wcf.igdb_integration.bbcode.author_rating');
            embed.querySelector('.igdbIntegrationGameEmbedButtons')?.before(ratingElement);
        }
        ratingElement.innerHTML = '';
        for (let i = 0; i < rating; i++) {
            // Add star icon
            const starIcon = document.createElement('fa-icon');
            starIcon.size = 16;
            starIcon.setIcon('star', true);
            ratingElement.appendChild(starIcon);
        }
    }
    function showGamePlayerListDialog(embed, gameId) {
        let form = new Dialog_1.default('gamePlayerListDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGamePlayerListDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
            },
            dialog: {
                title: embed.dataset.gameTitle || (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_player_list_title')
            }
        });
        form.open();
    }
    function showGameUserEditDialog(embed, gameId) {
        let form = new Dialog_1.default('gameUserEditDialog' + gameId, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction', 'getGameUserEditDialog', {
            destroyOnClose: true,
            actionParameters: {
                gameId: gameId,
            },
            dialog: {
                title: embed.dataset.gameTitle || (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_user_edit_title'),
                onShow: (content) => (0, IgdbIntegrationGameDialog_1.initGameUserEditDialogEvents)(content)
            },
            submitActionName: 'submitGameUserEditDialog',
            successCallback(returnValues) {
                updateEmbeds(returnValues);
                (0, Notification_1.show)();
            }
        });
        form.open();
    }
    async function quickToggleGame(button, embed, gameId) {
        const isRemoval = button.dataset.isOwned === '1';
        if (isRemoval && !(await (0, IgdbIntegrationGameDialog_1.confirmGameRemoval)(embed.dataset.gameTitle || ''))) {
            return;
        }
        const actionName = isRemoval ? 'quickRemoveGame' : 'quickAddGame';
        const returnValues = await (0, Ajax_1.dboAction)(actionName, 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
            .payload({
            gameId: gameId,
        })
            .dispatch();
        updateEmbeds(returnValues);
        (0, Notification_1.show)();
    }
    function setup() {
        // The bbcode can occur any number of times on a page and its output is
        // also inserted dynamically, e.g. by the message preview, so the buttons
        // are handled by a single delegated listener
        if (initialized) {
            return;
        }
        initialized = true;
        document.addEventListener('click', (event) => {
            const target = event.target;
            // The cover is a second, larger target for the details dialog next to
            // the button, matching the cover of the game list
            const editTarget = target.closest('.igdbIntegrationGameEmbedEdit, .igdbIntegrationGameEmbedCover');
            if (editTarget !== null) {
                const embed = editTarget.closest('.igdbIntegrationGameEmbed');
                if (embed !== null) {
                    showGameUserEditDialog(embed, parseInt(embed.dataset.gameId || '0', 10));
                }
                return;
            }
            const quickAddButton = target.closest('.igdbIntegrationGameEmbedQuickAdd');
            if (quickAddButton !== null) {
                const embed = quickAddButton.closest('.igdbIntegrationGameEmbed');
                if (embed !== null) {
                    void quickToggleGame(quickAddButton, embed, parseInt(embed.dataset.gameId || '0', 10));
                }
                return;
            }
            const playerListButton = target.closest('.igdbIntegrationGameEmbedPlayerList');
            if (playerListButton !== null) {
                const embed = playerListButton.closest('.igdbIntegrationGameEmbed');
                if (embed !== null) {
                    showGamePlayerListDialog(embed, parseInt(embed.dataset.gameId || '0', 10));
                }
            }
        });
        setupPlatformListDragScrolling();
    }
    /**
     * Lets the single-line platform list of the embed be scrolled by dragging
     * it with the mouse. Touch devices scroll it natively, so only mouse
     * pointers are handled.
     */
    function setupPlatformListDragScrolling() {
        let list = null;
        let startX = 0;
        let startScrollLeft = 0;
        document.addEventListener('pointerdown', (event) => {
            if (event.pointerType !== 'mouse' || event.button !== 0) {
                return;
            }
            list = event.target.closest('.igdbIntegrationGameEmbed .igdbIntegrationGamePlatformList');
            if (list === null || list.scrollWidth <= list.clientWidth) {
                list = null;
                return;
            }
            startX = event.clientX;
            startScrollLeft = list.scrollLeft;
            list.setPointerCapture(event.pointerId);
            list.classList.add('igdbIntegrationGamePlatformListDragging');
            event.preventDefault();
        });
        document.addEventListener('pointermove', (event) => {
            if (list !== null) {
                list.scrollLeft = startScrollLeft - (event.clientX - startX);
            }
        });
        const stopDragging = () => {
            list?.classList.remove('igdbIntegrationGamePlatformListDragging');
            list = null;
        };
        document.addEventListener('pointerup', stopDragging);
        document.addEventListener('pointercancel', stopDragging);
    }
});
