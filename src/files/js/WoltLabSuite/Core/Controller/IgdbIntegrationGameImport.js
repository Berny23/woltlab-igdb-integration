/**
 * Provides the game import box on the game list page: importing the owned
 * games of a Steam account and importing IGDB list export files.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameImport
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
    function createProgressDialog(max) {
        const dialog = (0, Dialog_1.dialogFactory)()
            .fromHtml('<div class="section"><p id="gameImportProgressText">&nbsp;</p><progress id="gameImportProgressBar" style="width: 100%" value="0" max="' + max + '"></progress></div>')
            .withoutControls();
        const text = dialog.content.querySelector('#gameImportProgressText');
        const bar = dialog.content.querySelector('#gameImportProgressBar');
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_title'));
        return { dialog, text, bar };
    }
    /**
     * Runs all Steam import steps sequentially while showing the progress, then
     * presents the summary.
     */
    async function runSteamImportSteps(start) {
        if (start.failed) {
            showResultDialog({ failed: true, importedCount: 0, alreadyOwnedCount: 0, unmatched: [], ambiguous: [] });
            return;
        }
        const progress = createProgressDialog(start.batchCount + 2);
        try {
            // Phase 1: batched IGDB requests for the Steam app ids
            for (let batch = 1; batch <= start.batchCount; batch++) {
                progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_batches', {
                    current: batch,
                    total: start.batchCount,
                });
                await (0, Ajax_1.dboAction)('processSteamImportBatch', ACTION_CLASS).dispatch();
                progress.bar.value = batch;
            }
            // Phase 2: per-title searches for the remaining games; the total is
            // only known after the first step
            let search;
            do {
                search = (await (0, Ajax_1.dboAction)('processSteamImportSearch', ACTION_CLASS).dispatch());
                progress.bar.max = start.batchCount + Math.ceil(search.searchTotal / 5) + 1;
                progress.bar.value = start.batchCount + Math.ceil(search.searched / 5);
                if (search.searchTotal > 0) {
                    progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_search', {
                        current: Math.min(search.searched, search.searchTotal),
                        total: search.searchTotal,
                    });
                }
            } while (!search.done);
            // Phase 3: roman numeral pass and summary
            progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_finalize');
            const result = (await (0, Ajax_1.dboAction)('finishSteamImport', ACTION_CLASS).dispatch());
            progress.bar.value = progress.bar.max;
            progress.dialog.close();
            showResultDialog(result);
        }
        catch (e) {
            // The AJAX error dialog is shown by the API itself
            progress.dialog.close();
            throw e;
        }
    }
    /**
     * Runs all IGDB list import steps sequentially while showing the progress,
     * then presents the summary. Games that IGDB does not know anymore are listed
     * by the name found in the file.
     */
    async function runIgdbImportSteps(gameIds, gameNames) {
        const start = (await (0, Ajax_1.dboAction)('startIgdbImport', ACTION_CLASS)
            .payload({ idList: gameIds.join(',') })
            .dispatch());
        const progress = createProgressDialog(start.batchCount + 1);
        try {
            for (let batch = 1; batch <= start.batchCount; batch++) {
                progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_batches', {
                    current: batch,
                    total: start.batchCount,
                });
                await (0, Ajax_1.dboAction)('processIgdbImportBatch', ACTION_CLASS).dispatch();
                progress.bar.value = batch;
            }
            progress.text.textContent = (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_progress_finalize');
            const result = (await (0, Ajax_1.dboAction)('finishIgdbImport', ACTION_CLASS).dispatch());
            progress.bar.value = progress.bar.max;
            progress.dialog.close();
            showResultDialog({
                failed: result.failed,
                importedCount: result.importedCount,
                alreadyOwnedCount: result.alreadyOwnedCount,
                unmatched: result.unmatchedIds.map((gameId) => gameNames.get(gameId) ?? 'IGDB #' + gameId),
                ambiguous: [],
            });
        }
        catch (e) {
            // The AJAX error dialog is shown by the API itself
            progress.dialog.close();
            throw e;
        }
    }
    /**
     * Parses CSV text into records, honoring quoted fields that may contain
     * commas, quotes and line breaks.
     */
    function parseCsv(text) {
        const rows = [];
        let row = [];
        let field = '';
        let inQuotes = false;
        for (let i = 0; i < text.length; i++) {
            const character = text[i];
            if (inQuotes) {
                if (character === '"') {
                    if (text[i + 1] === '"') {
                        field += '"';
                        i++;
                    }
                    else {
                        inQuotes = false;
                    }
                }
                else {
                    field += character;
                }
            }
            else if (character === '"') {
                inQuotes = true;
            }
            else if (character === ',') {
                row.push(field);
                field = '';
            }
            else if (character === '\n') {
                row.push(field.endsWith('\r') ? field.slice(0, -1) : field);
                rows.push(row);
                row = [];
                field = '';
            }
            else {
                field += character;
            }
        }
        if (field !== '' || row.length > 0) {
            row.push(field.endsWith('\r') ? field.slice(0, -1) : field);
            rows.push(row);
        }
        return rows;
    }
    /**
     * Extracts the game ids and names from an IGDB list export file, or null if
     * the file is not a valid export.
     */
    function parseIgdbListExport(text) {
        const rows = parseCsv(text.replace(/^﻿/, ''));
        if (rows.length < 2) {
            return null;
        }
        const header = rows[0].map((column) => column.trim().toLowerCase());
        const idIndex = header.indexOf('id');
        const nameIndex = header.indexOf('game');
        if (idIndex === -1) {
            return null;
        }
        const gameIds = [];
        const gameNames = new Map();
        for (let i = 1; i < rows.length; i++) {
            const gameId = parseInt(rows[i][idIndex], 10);
            if (Number.isInteger(gameId) && gameId > 0 && !gameNames.has(gameId)) {
                gameIds.push(gameId);
                gameNames.set(gameId, nameIndex !== -1 ? (rows[i][nameIndex] ?? '') : '');
            }
        }
        return gameIds.length > 0 ? { gameIds, gameNames } : null;
    }
    function showInvalidFileDialog() {
        const dialog = (0, Dialog_1.dialogFactory)()
            .fromHtml('<p>' + (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.igdb_import_invalid_file') + '</p>')
            .asAlert();
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.igdb_import_title'));
    }
    async function handleIgdbListFile(file) {
        const parsed = parseIgdbListExport(await file.text());
        if (parsed === null) {
            showInvalidFileDialog();
            return;
        }
        const dialog = (0, Dialog_1.dialogFactory)()
            .fromHtml('<p>' + (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.igdb_import_confirm', { count: parsed.gameIds.length }) + '</p>')
            .asConfirmation();
        dialog.addEventListener('primary', () => {
            void runIgdbImportSteps(parsed.gameIds, parsed.gameNames);
        });
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.igdb_import_title'));
    }
    function openSteamImportDialog() {
        const form = new Dialog_2.default('steamImportDialog', ACTION_CLASS, 'getSteamImportDialog', {
            destroyOnClose: true,
            dialog: {
                title: (0, Language_1.getPhrase)('wcf.igdb_integration.dialog.steam_import_title')
            },
            submitActionName: 'startSteamImport',
            successCallback(returnValues) {
                void runSteamImportSteps(returnValues);
            }
        });
        form.open();
    }
    function init(steamAutoOpen, gameListUrl) {
        cleanGameListUrl = gameListUrl;
        const steamButton = document.getElementById('steamImportButton');
        steamButton?.addEventListener('click', () => openSteamImportDialog());
        if (steamAutoOpen && steamButton !== null) {
            openSteamImportDialog();
        }
        const fileInput = document.getElementById('igdbImportFileInput');
        const fileButton = document.getElementById('igdbImportButton');
        fileButton?.addEventListener('click', () => fileInput?.click());
        fileInput?.addEventListener('change', () => {
            const file = fileInput.files?.[0];
            // Reset so that selecting the same file again triggers a new change event
            fileInput.value = '';
            if (file !== undefined) {
                void handleIgdbListFile(file);
            }
        });
    }
});
