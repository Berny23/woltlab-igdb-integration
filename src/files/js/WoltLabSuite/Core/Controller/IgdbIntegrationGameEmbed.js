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
                if (!response.isOwned) {
                    // The new rating is unknown after re-adding, so the stars stay
                    // hidden until the next page load
                    embed.querySelector('.igdbIntegrationGameEmbedAuthorRating')?.remove();
                }
            }
        });
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
    async function quickToggleGame(button, gameId) {
        const actionName = button.dataset.isOwned === '1' ? 'quickRemoveGame' : 'quickAddGame';
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
            const editButton = target.closest('.igdbIntegrationGameEmbedEdit');
            if (editButton !== null) {
                const embed = editButton.closest('.igdbIntegrationGameEmbed');
                if (embed !== null) {
                    showGameUserEditDialog(embed, parseInt(embed.dataset.gameId || '0', 10));
                }
                return;
            }
            const quickAddButton = target.closest('.igdbIntegrationGameEmbedQuickAdd');
            if (quickAddButton !== null) {
                const embed = quickAddButton.closest('.igdbIntegrationGameEmbed');
                if (embed !== null) {
                    void quickToggleGame(quickAddButton, parseInt(embed.dataset.gameId || '0', 10));
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
    }
});
