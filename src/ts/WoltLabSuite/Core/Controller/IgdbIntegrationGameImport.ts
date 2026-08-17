/**
 * Provides the game import box on the game list page: importing the owned
 * games of a Steam account or a public GOG profile and importing IGDB list
 * export files.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameImport
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { dialogFactory } from "WoltLabSuite/Core/Component/Dialog";
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

interface SteamImportData {
	steamId: string;
	gameCount: number;
	requestFailed: boolean;
}

interface GogImportData {
	gogUsername: string;
	gameCount: number;
	requestFailed: boolean;
}

interface GogStartResult {
	failed: boolean;
	pageCount: number;
	gameCount: number;
}

interface GogFetchResult {
	failed: boolean;
	currentPage: number;
	pageCount: number;
	done: boolean;
	batchCount: number;
}

/**
 * Pattern of a valid GOG username, mirroring the server-side validation.
 */
const GOG_USERNAME_REGEX = /^[a-zA-Z0-9._+-]{1,60}$/;

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

interface IgdbImportResult {
	failed: boolean;
	importedCount: number;
	alreadyOwnedCount: number;
	unmatchedIds: number[];
}

function buildNotice(type: string, text: string, names?: string[]): string {
	let html = '<woltlab-core-notice type="' + type + '">' + text;
	if (names !== undefined && names.length > 0) {
		html += '<ul class="nativeList">' + names.map((name) => '<li>' + escapeHTML(name) + '</li>').join('') + '</ul>';
	}
	return html + '</woltlab-core-notice>';
}

function showResultDialog(result: ImportResult, failedPhrase = 'wcf.igdb_integration.dialog.steam_import_result_failed'): void {
	let html: string;
	if (result.failed) {
		html = buildNotice('error', getPhrase(failedPhrase));
	} else {
		html = '';
		if (result.importedCount > 0) {
			html += buildNotice('success', getPhrase('wcf.igdb_integration.dialog.steam_import_result_imported', { count: result.importedCount }));
		}
		if (result.alreadyOwnedCount > 0) {
			html += buildNotice('info', getPhrase('wcf.igdb_integration.dialog.steam_import_result_already_owned', { count: result.alreadyOwnedCount }));
		}
		if (result.ambiguous.length > 0) {
			html += buildNotice('warning', getPhrase('wcf.igdb_integration.dialog.steam_import_result_ambiguous', { count: result.ambiguous.length }), result.ambiguous);
		}
		if (result.unmatched.length > 0) {
			html += buildNotice('warning', getPhrase('wcf.igdb_integration.dialog.steam_import_result_unmatched', { count: result.unmatched.length }), result.unmatched);
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

function createProgressDialog(max: number, titlePhrase = 'wcf.igdb_integration.dialog.steam_import_progress_title') {
	const dialog = dialogFactory()
		.fromHtml('<div class="section"><p id="gameImportProgressText">&nbsp;</p><progress id="gameImportProgressBar" style="width: 100%" value="0" max="' + max + '"></progress></div>')
		.withoutControls();
	const text = dialog.content.querySelector('#gameImportProgressText') as HTMLElement;
	const bar = dialog.content.querySelector('#gameImportProgressBar') as HTMLProgressElement;
	dialog.show(getPhrase(titlePhrase));

	return { dialog, text, bar };
}

/**
 * Runs all Steam import steps sequentially while showing the progress, then
 * presents the summary.
 */
async function runSteamImportSteps(start: StartResult): Promise<void> {
	if (start.failed) {
		showResultDialog({ failed: true, importedCount: 0, alreadyOwnedCount: 0, unmatched: [], ambiguous: [] });
		return;
	}

	const progress = createProgressDialog(start.batchCount + 2);

	try {
		// Phase 1: batched IGDB requests for the Steam app ids
		for (let batch = 1; batch <= start.batchCount; batch++) {
			progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_batches', {
				current: batch,
				total: start.batchCount,
			});
			await dboAction('processSteamImportBatch', ACTION_CLASS).dispatch();
			progress.bar.value = batch;
		}

		// Phase 2: per-title searches for the remaining games; the total is
		// only known after the first step
		let search: SearchResult;
		do {
			search = (await dboAction('processSteamImportSearch', ACTION_CLASS).dispatch()) as SearchResult;
			progress.bar.max = start.batchCount + Math.ceil(search.searchTotal / 5) + 1;
			progress.bar.value = start.batchCount + Math.ceil(search.searched / 5);
			if (search.searchTotal > 0) {
				progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_search', {
					current: Math.min(search.searched, search.searchTotal),
					total: search.searchTotal,
				});
			}
		} while (!search.done);

		// Phase 3: roman numeral pass and summary
		progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_finalize');
		const result = (await dboAction('finishSteamImport', ACTION_CLASS).dispatch()) as ImportResult;
		progress.bar.value = progress.bar.max;

		progress.dialog.close();
		showResultDialog(result);
	} catch (e) {
		// The AJAX error dialog is shown by the API itself
		progress.dialog.close();
		throw e;
	}
}

/**
 * Runs all GOG import steps sequentially while showing the progress, then
 * presents the summary. The library pages are fetched step by step, so the
 * batch count is only known after the last fetch step.
 */
async function runGogImportSteps(start: GogStartResult): Promise<void> {
	const failedPhrase = 'wcf.igdb_integration.dialog.gog_import_result_failed';
	if (start.failed) {
		showResultDialog({ failed: true, importedCount: 0, alreadyOwnedCount: 0, unmatched: [], ambiguous: [] }, failedPhrase);
		return;
	}

	const progress = createProgressDialog(start.pageCount + 2, 'wcf.igdb_integration.dialog.gog_import_progress_title');

	try {
		// Phase 1: fetch the remaining pages of the public GOG games list; the
		// first page was already fetched by startGogImport
		let fetch: GogFetchResult = { failed: false, currentPage: 1, pageCount: start.pageCount, done: false, batchCount: 0 };
		progress.bar.value = 1;
		while (!fetch.done) {
			progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.gog_import_progress_pages', {
				current: fetch.currentPage,
				total: fetch.pageCount,
			});
			fetch = (await dboAction('processGogImportFetch', ACTION_CLASS).dispatch()) as GogFetchResult;
			progress.bar.value = fetch.currentPage;
		}
		if (fetch.failed) {
			progress.dialog.close();
			showResultDialog({ failed: true, importedCount: 0, alreadyOwnedCount: 0, unmatched: [], ambiguous: [] }, failedPhrase);
			return;
		}

		// Phase 2: batched IGDB requests for the GOG product ids
		progress.bar.max = fetch.pageCount + fetch.batchCount + 2;
		for (let batch = 1; batch <= fetch.batchCount; batch++) {
			progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_batches', {
				current: batch,
				total: fetch.batchCount,
			});
			await dboAction('processGogImportBatch', ACTION_CLASS).dispatch();
			progress.bar.value = fetch.pageCount + batch;
		}

		// Phase 3: per-title searches for the remaining games; the total is
		// only known after the first step
		let search: SearchResult;
		do {
			search = (await dboAction('processGogImportSearch', ACTION_CLASS).dispatch()) as SearchResult;
			progress.bar.max = fetch.pageCount + fetch.batchCount + Math.ceil(search.searchTotal / 5) + 1;
			progress.bar.value = fetch.pageCount + fetch.batchCount + Math.ceil(search.searched / 5);
			if (search.searchTotal > 0) {
				progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_search', {
					current: Math.min(search.searched, search.searchTotal),
					total: search.searchTotal,
				});
			}
		} while (!search.done);

		// Phase 4: roman numeral pass and summary
		progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_finalize');
		const result = (await dboAction('finishGogImport', ACTION_CLASS).dispatch()) as ImportResult;
		progress.bar.value = progress.bar.max;

		progress.dialog.close();
		showResultDialog(result, failedPhrase);
	} catch (e) {
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
async function runIgdbImportSteps(gameIds: number[], gameNames: Map<number, string>): Promise<void> {
	const start = (await dboAction('startIgdbImport', ACTION_CLASS)
		.payload({ idList: gameIds.join(',') })
		.dispatch()) as StartResult;

	const progress = createProgressDialog(start.batchCount + 1);

	try {
		for (let batch = 1; batch <= start.batchCount; batch++) {
			progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_batches', {
				current: batch,
				total: start.batchCount,
			});
			await dboAction('processIgdbImportBatch', ACTION_CLASS).dispatch();
			progress.bar.value = batch;
		}

		progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_finalize');
		const result = (await dboAction('finishIgdbImport', ACTION_CLASS).dispatch()) as IgdbImportResult;
		progress.bar.value = progress.bar.max;

		progress.dialog.close();
		showResultDialog({
			failed: result.failed,
			importedCount: result.importedCount,
			alreadyOwnedCount: result.alreadyOwnedCount,
			unmatched: result.unmatchedIds.map((gameId) => gameNames.get(gameId) ?? 'IGDB #' + gameId),
			ambiguous: [],
		});
	} catch (e) {
		// The AJAX error dialog is shown by the API itself
		progress.dialog.close();
		throw e;
	}
}

/**
 * Parses CSV text into records, honoring quoted fields that may contain
 * commas, quotes and line breaks.
 */
function parseCsv(text: string): string[][] {
	const rows: string[][] = [];
	let row: string[] = [];
	let field = '';
	let inQuotes = false;

	for (let i = 0; i < text.length; i++) {
		const character = text[i];
		if (inQuotes) {
			if (character === '"') {
				if (text[i + 1] === '"') {
					field += '"';
					i++;
				} else {
					inQuotes = false;
				}
			} else {
				field += character;
			}
		} else if (character === '"') {
			inQuotes = true;
		} else if (character === ',') {
			row.push(field);
			field = '';
		} else if (character === '\n') {
			row.push(field.endsWith('\r') ? field.slice(0, -1) : field);
			rows.push(row);
			row = [];
			field = '';
		} else {
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
function parseIgdbListExport(text: string): { gameIds: number[]; gameNames: Map<number, string> } | null {
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

	const gameIds: number[] = [];
	const gameNames = new Map<number, string>();
	for (let i = 1; i < rows.length; i++) {
		const gameId = parseInt(rows[i][idIndex], 10);
		if (Number.isInteger(gameId) && gameId > 0 && !gameNames.has(gameId)) {
			gameIds.push(gameId);
			gameNames.set(gameId, nameIndex !== -1 ? (rows[i][nameIndex] ?? '') : '');
		}
	}

	return gameIds.length > 0 ? { gameIds, gameNames } : null;
}

function showInvalidFileDialog(): void {
	const dialog = dialogFactory()
		.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.igdb_import_invalid_file') + '</p>')
		.asAlert();
	dialog.show(getPhrase('wcf.igdb_integration.dialog.igdb_import_title'));
}

async function handleIgdbListFile(file: File): Promise<void> {
	const parsed = parseIgdbListExport(await file.text());
	if (parsed === null) {
		showInvalidFileDialog();
		return;
	}

	const dialog = dialogFactory()
		.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.igdb_import_confirm', { count: parsed.gameIds.length }) + '</p>')
		.asConfirmation();
	dialog.addEventListener('primary', () => {
		void runIgdbImportSteps(parsed.gameIds, parsed.gameNames);
	});
	dialog.show(getPhrase('wcf.igdb_integration.dialog.igdb_import_title'));
}

async function openSteamImportDialog(): Promise<void> {
	const data = (await dboAction('getSteamImportData', ACTION_CLASS).dispatch()) as SteamImportData;
	const title = getPhrase('wcf.igdb_integration.dialog.steam_import_title');

	if (data.requestFailed) {
		dialogFactory()
			.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.steam_import_request_failed') + '</p>')
			.asAlert()
			.show(title);
		return;
	}
	if (data.gameCount === 0) {
		dialogFactory()
			.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.steam_import_empty', { steamImportSteamId: data.steamId }) + '</p>')
			.asAlert()
			.show(title);
		return;
	}

	const dialog = dialogFactory()
		.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.steam_import_confirm', {
			steamImportSteamId: data.steamId,
			steamGameCount: data.gameCount,
		}) + '</p>')
		.asConfirmation();
	dialog.addEventListener('primary', () => {
		void (async () => {
			const start = (await dboAction('startSteamImport', ACTION_CLASS).dispatch()) as StartResult;
			await runSteamImportSteps(start);
		})();
	});
	dialog.show(title);
}

/**
 * Fetches the game count of the entered GOG profile and asks for confirmation
 * before starting the import.
 */
async function confirmGogImport(username: string): Promise<void> {
	const title = getPhrase('wcf.igdb_integration.dialog.gog_import_title');

	if (!GOG_USERNAME_REGEX.test(username)) {
		dialogFactory()
			.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.gog_import_request_failed', { gogImportUsername: username }) + '</p>')
			.asAlert()
			.show(title);
		return;
	}

	const data = (await dboAction('getGogImportData', ACTION_CLASS)
		.payload({ gogUsername: username })
		.dispatch()) as GogImportData;

	if (data.requestFailed) {
		dialogFactory()
			.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.gog_import_request_failed', { gogImportUsername: username }) + '</p>')
			.asAlert()
			.show(title);
		return;
	}
	if (data.gameCount === 0) {
		dialogFactory()
			.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.gog_import_empty', { gogImportUsername: username }) + '</p>')
			.asAlert()
			.show(title);
		return;
	}

	const dialog = dialogFactory()
		.fromHtml('<p>' + getPhrase('wcf.igdb_integration.dialog.gog_import_confirm', {
			gogImportUsername: username,
			gogGameCount: data.gameCount,
		}) + '</p>')
		.asConfirmation();
	dialog.addEventListener('primary', () => {
		void (async () => {
			const start = (await dboAction('startGogImport', ACTION_CLASS)
				.payload({ gogUsername: username })
				.dispatch()) as GogStartResult;
			await runGogImportSteps(start);
		})();
	});
	dialog.show(title);
}

function openGogImportDialog(): void {
	const dialog = dialogFactory()
		.fromHtml('<dl><dt><label for="gogImportUsername">' + getPhrase('wcf.igdb_integration.dialog.gog_import_username')
			+ '</label></dt><dd><input type="text" id="gogImportUsername" class="long" maxlength="60" autocomplete="off" required autofocus></dd></dl>')
		.asPrompt();
	const input = dialog.content.querySelector('#gogImportUsername') as HTMLInputElement;
	dialog.addEventListener('primary', () => {
		const username = input.value.trim();
		if (username !== '') {
			void confirmGogImport(username);
		}
	});
	dialog.show(getPhrase('wcf.igdb_integration.dialog.gog_import_title'));
	input.focus();
}

export function init(steamAutoOpen: boolean, gameListUrl: string) {
	cleanGameListUrl = gameListUrl;

	const steamButton = document.getElementById('steamImportButton');
	steamButton?.addEventListener('click', () => void openSteamImportDialog());

	if (steamAutoOpen && steamButton !== null) {
		void openSteamImportDialog();
	}

	const gogButton = document.getElementById('gogImportButton');
	gogButton?.addEventListener('click', () => openGogImportDialog());

	const fileInput = document.getElementById('igdbImportFileInput') as HTMLInputElement | null;
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
