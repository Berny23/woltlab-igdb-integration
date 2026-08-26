/**
 * Drives the "Refresh from IGDB" interactions of the ACP game list, for a
 * single game and for a bulk selection. Games IGDB no longer knows are listed
 * in a warning dialog that offers to delete them.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameRefresh
 */
define(["require", "exports", "WoltLabSuite/Core/Api/DeleteObject", "WoltLabSuite/Core/Api/PostObject", "WoltLabSuite/Core/Component/Dialog", "WoltLabSuite/Core/Component/Interaction/Confirmation", "WoltLabSuite/Core/Component/Snackbar", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/StringUtil"], function (require, exports, DeleteObject_1, PostObject_1, Dialog_1, Confirmation_1, Snackbar_1, Language_1, StringUtil_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.setupRefreshInteraction = setupRefreshInteraction;
    exports.setupBulkRefreshInteraction = setupBulkRefreshInteraction;
    /**
     * Reloads the grid row of the given game, if it is currently shown.
     */
    function invalidateRow(container, gameId) {
        container
            .querySelector('[data-object-id="' + gameId + '"]')
            ?.dispatchEvent(new CustomEvent('interaction:invalidate', { bubbles: true, detail: { _reloadPage: 'false' } }));
    }
    /**
     * Deletes the given games and removes their grid rows.
     */
    async function deleteMissingGames(container, missingGames, deleteEndpoint) {
        for (const game of missingGames) {
            (await (0, DeleteObject_1.deleteObject)(deleteEndpoint.replace('{id}', String(game.gameId)))).unwrap();
            container
                .querySelector('[data-object-id="' + game.gameId + '"]')
                ?.dispatchEvent(new CustomEvent('interaction:remove', { bubbles: true }));
        }
        (0, Snackbar_1.showSuccessSnackbar)((0, Language_1.getPhrase)('wcf.global.success.delete'));
    }
    /**
     * Shows the games IGDB did not return in a warning dialog; confirming deletes
     * them from the database.
     */
    function showMissingGamesDialog(container, missingGames, deleteEndpoint) {
        const items = missingGames.map((game) => {
            const title = game.releaseYear !== null ? game.name + ' (' + game.releaseYear + ')' : game.name;
            return '<li>' + (0, StringUtil_1.escapeHTML)(title) + '</li>';
        });
        const html = '<woltlab-core-notice type="warning">'
            + '<p>' + (0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh.missing_message')) + '</p>'
            + '<ul class="nativeList">' + items.join('') + '</ul>'
            + '</woltlab-core-notice>';
        const dialog = (0, Dialog_1.dialogFactory)()
            .fromHtml(html)
            .asConfirmation({ primary: (0, Language_1.getPhrase)('wcf.global.button.delete') });
        dialog.addEventListener('primary', () => {
            void deleteMissingGames(container, missingGames, deleteEndpoint);
        });
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh.missing_title'));
    }
    /**
     * Presents the outcome of a refresh: the plain success snackbar, or the
     * dialog listing the games that are missing on IGDB.
     */
    function finishRefresh(container, result, deleteEndpoint) {
        if (result.missingGames.length > 0) {
            showMissingGamesDialog(container, result.missingGames, deleteEndpoint);
        }
        else {
            (0, Snackbar_1.showDefaultSuccessSnackbar)();
        }
    }
    async function handleRefresh(container, detail, deleteEndpoint) {
        const confirmationResult = await (0, Confirmation_1.handleConfirmation)(detail.objectName ?? '', detail.confirmationType, detail.confirmationMessage);
        if (!confirmationResult.result) {
            return;
        }
        const result = (await (0, PostObject_1.postObject)(detail.endpoint)).unwrap();
        // The endpoint of a single game ends with "/<id>/refresh"
        const gameId = parseInt(detail.endpoint.replace(/\/refresh$/, '').split('/').pop() ?? '0');
        invalidateRow(container, gameId);
        finishRefresh(container, result, deleteEndpoint);
    }
    async function handleBulkRefresh(container, detail, deleteEndpoint) {
        const confirmationResult = await (0, Confirmation_1.handleConfirmation)('', detail.confirmationType, detail.confirmationMessage);
        if (!confirmationResult.result) {
            return;
        }
        const objectIds = JSON.parse(detail.objectIds);
        const result = (await (0, PostObject_1.postObject)(detail.endpoint, { gameIds: objectIds })).unwrap();
        for (const objectId of objectIds) {
            invalidateRow(container, objectId);
        }
        container.dispatchEvent(new CustomEvent('interaction:reset-selection'));
        container.dispatchEvent(new CustomEvent('interaction:bulk-completed'));
        finishRefresh(container, result, deleteEndpoint);
    }
    /**
     * Handles the refresh interaction of single games. "deleteEndpoint" is the
     * delete endpoint with an "{id}" placeholder.
     */
    function setupRefreshInteraction(identifier, container, deleteEndpoint) {
        container.addEventListener('interaction:execute', (event) => {
            const detail = event.detail;
            if (detail.interaction === identifier) {
                void handleRefresh(container, detail, deleteEndpoint);
            }
        });
    }
    /**
     * Handles the bulk refresh interaction. "deleteEndpoint" is the delete
     * endpoint with an "{id}" placeholder.
     */
    function setupBulkRefreshInteraction(identifier, container, deleteEndpoint) {
        container.addEventListener('bulk-interaction', (event) => {
            const detail = event.detail;
            if (detail.bulkInteraction === identifier) {
                void handleBulkRefresh(container, detail, deleteEndpoint);
            }
        });
    }
});
