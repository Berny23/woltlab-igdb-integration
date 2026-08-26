/**
 * Drives the "Refresh from IGDB" interactions of the ACP game list, for a
 * single game, for a bulk selection and for the whole database. Games IGDB no
 * longer knows are listed in a warning dialog that offers to delete them.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameRefresh
 */
define(["require", "exports", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Ajax/Backend", "WoltLabSuite/Core/Api/DeleteObject", "WoltLabSuite/Core/Api/Result", "WoltLabSuite/Core/Component/Dialog", "WoltLabSuite/Core/Component/Interaction/Confirmation", "WoltLabSuite/Core/Component/Snackbar", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/StringUtil"], function (require, exports, Ajax_1, Backend_1, DeleteObject_1, Result_1, Dialog_1, Confirmation_1, Snackbar_1, Language_1, StringUtil_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.setupRefreshAllButton = setupRefreshAllButton;
    exports.setupRefreshInteraction = setupRefreshInteraction;
    exports.setupBulkRefreshInteraction = setupBulkRefreshInteraction;
    const ACTION_CLASS = 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction';
    /**
     * Posts to a refresh endpoint and returns its response. Core's "postObject"
     * discards the response body, so the request is issued directly.
     */
    async function postRefresh(endpoint, payload) {
        let response;
        try {
            response = (await (0, Backend_1.prepareRequest)(endpoint).post(payload).fetchAsJson());
        }
        catch (e) {
            return (0, Result_1.apiResultFromError)(e);
        }
        return (0, Result_1.apiResultFromValue)(response);
    }
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
     * Presents the outcome of a refresh: the success snackbar, plus the dialog
     * listing the games that are missing on IGDB, if any.
     */
    function finishRefresh(container, result, deleteEndpoint) {
        (0, Snackbar_1.showDefaultSuccessSnackbar)();
        if (result.missingGames.length > 0) {
            showMissingGamesDialog(container, result.missingGames, deleteEndpoint);
        }
    }
    async function handleRefresh(container, detail, deleteEndpoint) {
        const confirmationResult = await (0, Confirmation_1.handleConfirmation)(detail.objectName ?? '', detail.confirmationType, detail.confirmationMessage);
        if (!confirmationResult.result) {
            return;
        }
        const result = (await postRefresh(detail.endpoint)).unwrap();
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
        const result = (await postRefresh(detail.endpoint, { gameIds: objectIds })).unwrap();
        for (const objectId of objectIds) {
            invalidateRow(container, objectId);
        }
        container.dispatchEvent(new CustomEvent('interaction:reset-selection'));
        container.dispatchEvent(new CustomEvent('interaction:bulk-completed'));
        finishRefresh(container, result, deleteEndpoint);
    }
    function createProgressDialog(max) {
        const dialog = (0, Dialog_1.dialogFactory)()
            .fromHtml('<div class="section"><p id="igdbIntegrationRefreshProgressText">&nbsp;</p><progress id="igdbIntegrationRefreshProgressBar" style="width: 100%" value="0" max="' + max + '"></progress></div>')
            .withoutControls();
        const text = dialog.content.querySelector('#igdbIntegrationRefreshProgressText');
        const bar = dialog.content.querySelector('#igdbIntegrationRefreshProgressBar');
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.progress_title'));
        return { dialog, text, bar };
    }
    /**
     * Refreshes the whole game database in batches of one IGDB request each while
     * showing the progress, then reloads the grid and presents the games IGDB no
     * longer knows.
     */
    async function runAcpRefreshSteps(container, staleOnly, deleteEndpoint) {
        const start = (await (0, Ajax_1.dboAction)('startAcpRefresh', ACTION_CLASS)
            .payload({ staleOnly: staleOnly ? 1 : 0 })
            .dispatch());
        if (start.gameCount === 0) {
            (0, Dialog_1.dialogFactory)()
                .fromHtml('<p>' + (0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.nothing_to_do')) + '</p>')
                .asAlert()
                .show((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all'));
            return;
        }
        const progress = createProgressDialog(start.batchCount + 1);
        let result;
        try {
            for (let batch = 1; batch <= start.batchCount; batch++) {
                progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_batches', {
                    current: batch,
                    total: start.batchCount,
                });
                const step = (await (0, Ajax_1.dboAction)('processAcpRefreshBatch', ACTION_CLASS).dispatch());
                progress.bar.value = batch;
                if (step.failed) {
                    break;
                }
            }
            progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.progress_finalize');
            result = (await (0, Ajax_1.dboAction)('finishAcpRefresh', ACTION_CLASS).dispatch());
            progress.bar.value = progress.bar.max;
        }
        finally {
            // The AJAX error dialog of a failed request is shown by the API itself
            progress.dialog.close();
        }
        container.dispatchEvent(new CustomEvent('interaction:invalidate-all'));
        if (result.failed) {
            (0, Dialog_1.dialogFactory)()
                .fromHtml('<woltlab-core-notice type="error">' + (0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.failed')) + '</woltlab-core-notice>')
                .asAlert()
                .show((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all'));
            return;
        }
        finishRefresh(container, result, deleteEndpoint);
    }
    /**
     * Opens the options dialog of the "refresh all games" button.
     */
    function showRefreshAllDialog(container, deleteEndpoint) {
        const html = '<p>' + (0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.description')) + '</p>'
            + '<dl><dt></dt><dd><label><input type="checkbox" id="igdbIntegrationRefreshStaleOnly" checked> '
            + (0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.stale_only')) + '</label></dd></dl>';
        const dialog = (0, Dialog_1.dialogFactory)()
            .fromHtml(html)
            .asConfirmation({ primary: (0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all.start') });
        dialog.addEventListener('primary', () => {
            const staleOnly = dialog.content.querySelector('#igdbIntegrationRefreshStaleOnly').checked;
            void runAcpRefreshSteps(container, staleOnly, deleteEndpoint);
        });
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.game.refresh_all'));
    }
    /**
     * Wires the "refresh all games" button of the ACP game list. "containerId" is
     * the id of the grid table, "deleteEndpoint" the delete endpoint with an
     * "{id}" placeholder.
     */
    function setupRefreshAllButton(buttonId, containerId, deleteEndpoint) {
        const button = document.getElementById(buttonId);
        const container = document.getElementById(containerId);
        if (button === null || container === null) {
            return;
        }
        button.addEventListener('click', () => {
            showRefreshAllDialog(container, deleteEndpoint);
        });
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
