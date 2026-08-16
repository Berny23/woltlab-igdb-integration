/**
 * Provides the Steam library import dialog on the game list page.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationSteamImport
 */
define(["require", "exports", "tslib", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Component/Dialog", "WoltLabSuite/Core/Form/Builder/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/StringUtil"], function (require, exports, tslib_1, Ajax_1, Dialog_1, Dialog_2, Language_1, StringUtil_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.init = init;
    Dialog_2 = tslib_1.__importDefault(Dialog_2);
    const ACTION_CLASS = 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction';
    /**
     * Plain game list url without any filter or steamImport parameters.
     */
    let cleanGameListUrl = '';
    function buildNotice(type, text, names) {
        let html = '<woltlab-core-notice type="' + type + '">' + text;
        if (names !== undefined && names.length > 0) {
            html += '<ul>' + names.map((name) => '<li>' + (0, StringUtil_1.escapeHTML)(name) + '</li>').join('') + '</ul>';
        }
        return html + '</woltlab-core-notice>';
    }
    function showResultDialog(result) {
        let html;
        if (result.failed) {
            html = buildNotice('error', (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_result_failed'));
        }
        else {
            html = buildNotice('success', (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_result_imported', { count: result.importedCount }));
            if (result.alreadyOwnedCount > 0) {
                html += buildNotice('info', (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_result_already_owned', { count: result.alreadyOwnedCount }));
            }
            if (result.ambiguous.length > 0) {
                html += buildNotice('warning', (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_result_ambiguous', { count: result.ambiguous.length }), result.ambiguous);
            }
            if (result.unmatched.length > 0) {
                html += buildNotice('error', (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_result_unmatched', { count: result.unmatched.length }), result.unmatched);
            }
        }
        const dialog = (0, Dialog_1.dialogFactory)().fromHtml(html).asAlert();
        // Reload to show the imported games and the updated owner counts, without
        // any filter parameters and without steamImport=1 reopening the dialog
        dialog.addEventListener('afterClose', () => {
            if (cleanGameListUrl !== '') {
                window.location.href = cleanGameListUrl;
            }
            else {
                window.location.reload();
            }
        });
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_result_title'));
    }
    /**
     * Runs all import steps sequentially while showing the progress, then
     * presents the summary.
     */
    async function runImportSteps(start) {
        if (start.failed) {
            showResultDialog({ failed: true, importedCount: 0, alreadyOwnedCount: 0, unmatched: [], ambiguous: [] });
            return;
        }
        const progressDialog = (0, Dialog_1.dialogFactory)()
            .fromHtml('<div class="section"><p id="steamImportProgressText">&nbsp;</p><progress id="steamImportProgressBar" style="width: 100%" value="0" max="' + (start.batchCount + 2) + '"></progress></div>')
            .withoutControls();
        const progressText = progressDialog.content.querySelector('#steamImportProgressText');
        const progressBar = progressDialog.content.querySelector('#steamImportProgressBar');
        progressDialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_title'));
        try {
            // Phase 1: batched IGDB requests for the Steam app ids
            for (let batch = 1; batch <= start.batchCount; batch++) {
                progressText.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_batches', {
                    current: batch,
                    total: start.batchCount,
                });
                await (0, Ajax_1.dboAction)('processSteamImportBatch', ACTION_CLASS).dispatch();
                progressBar.value = batch;
            }
            // Phase 2: per-title searches for the remaining games; the total is
            // only known after the first step
            let search;
            do {
                search = (await (0, Ajax_1.dboAction)('processSteamImportSearch', ACTION_CLASS).dispatch());
                progressBar.max = start.batchCount + Math.ceil(search.searchTotal / 5) + 1;
                progressBar.value = start.batchCount + Math.ceil(search.searched / 5);
                if (search.searchTotal > 0) {
                    progressText.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_search', {
                        current: Math.min(search.searched, search.searchTotal),
                        total: search.searchTotal,
                    });
                }
            } while (!search.done);
            // Phase 3: roman numeral pass and summary
            progressText.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_finalize');
            const result = (await (0, Ajax_1.dboAction)('finishSteamImport', ACTION_CLASS).dispatch());
            progressBar.value = progressBar.max;
            progressDialog.close();
            showResultDialog(result);
        }
        catch (e) {
            // The AJAX error dialog is shown by the API itself
            progressDialog.close();
            throw e;
        }
    }
    function openImportDialog() {
        const form = new Dialog_2.default('steamImportDialog', ACTION_CLASS, 'getSteamImportDialog', {
            destroyOnClose: true,
            dialog: {
                title: (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_title')
            },
            submitActionName: 'startSteamImport',
            successCallback(returnValues) {
                void runImportSteps(returnValues);
            }
        });
        form.open();
    }
    function init(autoOpen, gameListUrl) {
        cleanGameListUrl = gameListUrl;
        const button = document.getElementById('steamImportButton');
        button?.addEventListener('click', () => openImportDialog());
        if (autoOpen && button !== null) {
            openImportDialog();
        }
    }
});
