/**
 * Provides the Steam library import dialog on the game list page.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationSteamImport
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { dialogFactory } from "WoltLabSuite/Core/Component/Dialog";
import FormBuilderDialog from "WoltLabSuite/Core/Form/Builder/Dialog";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { escapeHTML } from "WoltLabSuite/Core/StringUtil";

const ACTION_CLASS = 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction';

/**
 * Plain game list url without any filter or steamImport parameters.
 */
let cleanGameListUrl = '';

interface StartResult {
	failed: boolean;
	batchCount: number;
	gameCount: number;
}

interface SearchResult {
	searched: number;
	searchTotal: number;
	done: boolean;
}

interface ImportResult {
	failed: boolean;
	importedCount: number;
	alreadyOwnedCount: number;
	unmatched: string[];
	ambiguous: string[];
}

function buildNotice(type: string, text: string, names?: string[]): string {
	let html = '<woltlab-core-notice type="' + type + '">' + text;
	if (names !== undefined && names.length > 0) {
		html += '<ul>' + names.map((name) => '<li>' + escapeHTML(name) + '</li>').join('') + '</ul>';
	}
	return html + '</woltlab-core-notice>';
}

function showResultDialog(result: ImportResult): void {
	let html: string;
	if (result.failed) {
		html = buildNotice('error', getPhrase('wcf.igdb_integration.dialog.steam_import_result_failed'));
	} else {
		html = buildNotice('success', getPhrase('wcf.igdb_integration.dialog.steam_import_result_imported', { count: result.importedCount }));
		if (result.alreadyOwnedCount > 0) {
			html += buildNotice('info', getPhrase('wcf.igdb_integration.dialog.steam_import_result_already_owned', { count: result.alreadyOwnedCount }));
		}
		if (result.ambiguous.length > 0) {
			html += buildNotice('warning', getPhrase('wcf.igdb_integration.dialog.steam_import_result_ambiguous', { count: result.ambiguous.length }), result.ambiguous);
		}
		if (result.unmatched.length > 0) {
			html += buildNotice('error', getPhrase('wcf.igdb_integration.dialog.steam_import_result_unmatched', { count: result.unmatched.length }), result.unmatched);
		}
	}

	const dialog = dialogFactory().fromHtml(html).asAlert();
	// Reload to show the imported games and the updated owner counts, without
	// any filter parameters and without steamImport=1 reopening the dialog
	dialog.addEventListener('afterClose', () => {
		if (cleanGameListUrl !== '') {
			window.location.href = cleanGameListUrl;
		} else {
			window.location.reload();
		}
	});
	dialog.show(getPhrase('wcf.igdb_integration.dialog.steam_import_result_title'));
}

/**
 * Runs all import steps sequentially while showing the progress, then
 * presents the summary.
 */
async function runImportSteps(start: StartResult): Promise<void> {
	if (start.failed) {
		showResultDialog({ failed: true, importedCount: 0, alreadyOwnedCount: 0, unmatched: [], ambiguous: [] });
		return;
	}

	const progressDialog = dialogFactory()
		.fromHtml('<div class="section"><p id="steamImportProgressText">&nbsp;</p><progress id="steamImportProgressBar" style="width: 100%" value="0" max="' + (start.batchCount + 2) + '"></progress></div>')
		.withoutControls();
	const progressText = progressDialog.content.querySelector('#steamImportProgressText') as HTMLElement;
	const progressBar = progressDialog.content.querySelector('#steamImportProgressBar') as HTMLProgressElement;
	progressDialog.show(getPhrase('wcf.igdb_integration.dialog.steam_import_progress_title'));

	try {
		// Phase 1: batched IGDB requests for the Steam app ids
		for (let batch = 1; batch <= start.batchCount; batch++) {
			progressText.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_batches', {
				current: batch,
				total: start.batchCount,
			});
			await dboAction('processSteamImportBatch', ACTION_CLASS).dispatch();
			progressBar.value = batch;
		}

		// Phase 2: per-title searches for the remaining games; the total is
		// only known after the first step
		let search: SearchResult;
		do {
			search = (await dboAction('processSteamImportSearch', ACTION_CLASS).dispatch()) as SearchResult;
			progressBar.max = start.batchCount + Math.ceil(search.searchTotal / 5) + 1;
			progressBar.value = start.batchCount + Math.ceil(search.searched / 5);
			if (search.searchTotal > 0) {
				progressText.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_search', {
					current: Math.min(search.searched, search.searchTotal),
					total: search.searchTotal,
				});
			}
		} while (!search.done);

		// Phase 3: roman numeral pass and summary
		progressText.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_finalize');
		const result = (await dboAction('finishSteamImport', ACTION_CLASS).dispatch()) as ImportResult;
		progressBar.value = progressBar.max;

		progressDialog.close();
		showResultDialog(result);
	} catch (e) {
		// The AJAX error dialog is shown by the API itself
		progressDialog.close();
		throw e;
	}
}

function openImportDialog(): void {
	const form = new FormBuilderDialog(
		'steamImportDialog',
		ACTION_CLASS,
		'getSteamImportDialog', {
		destroyOnClose: true,
		dialog: {
			title: getPhrase('wcf.igdb_integration.dialog.steam_import_title')
		},
		submitActionName: 'startSteamImport',
		successCallback(returnValues) {
			void runImportSteps(returnValues as StartResult);
		}
	});

	form.open();
}

export function init(autoOpen: boolean, gameListUrl: string) {
	cleanGameListUrl = gameListUrl;

	const button = document.getElementById('steamImportButton');
	button?.addEventListener('click', () => openImportDialog());

	if (autoOpen && button !== null) {
		openImportDialog();
	}
}
