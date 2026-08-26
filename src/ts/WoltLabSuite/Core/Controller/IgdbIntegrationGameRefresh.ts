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

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { prepareRequest } from "WoltLabSuite/Core/Ajax/Backend";
import { deleteObject } from "WoltLabSuite/Core/Api/DeleteObject";
import { ApiResult, apiResultFromError, apiResultFromValue } from "WoltLabSuite/Core/Api/Result";
import { dialogFactory } from "WoltLabSuite/Core/Component/Dialog";
import { ConfirmationType, handleConfirmation } from "WoltLabSuite/Core/Component/Interaction/Confirmation";
import { showDefaultSuccessSnackbar, showSuccessSnackbar } from "WoltLabSuite/Core/Component/Snackbar";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { escapeHTML } from "WoltLabSuite/Core/StringUtil";

const ACTION_CLASS = 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction';

interface MissingGame {
	gameId: number;
	name: string;
	releaseYear: number | null;
}

interface RefreshResult {
	missingGames: MissingGame[];
}

/**
 * Posts to a refresh endpoint and returns its response. Core's "postObject"
 * discards the response body, so the request is issued directly.
 */
async function postRefresh(endpoint: string, payload?: Record<string, unknown>): Promise<ApiResult<RefreshResult>> {
	let response: RefreshResult;
	try {
		response = (await prepareRequest(endpoint).post(payload).fetchAsJson()) as RefreshResult;
	} catch (e) {
		return apiResultFromError(e);
	}

	return apiResultFromValue(response);
}

/**
 * Reloads the grid row of the given game, if it is currently shown.
 */
function invalidateRow(container: HTMLElement, gameId: number): void {
	container
		.querySelector('[data-object-id="' + gameId + '"]')
		?.dispatchEvent(new CustomEvent('interaction:invalidate', { bubbles: true, detail: { _reloadPage: 'false' } }));
}

/**
 * Deletes the given games and removes their grid rows.
 */
async function deleteMissingGames(container: HTMLElement, missingGames: MissingGame[], deleteEndpoint: string): Promise<void> {
	for (const game of missingGames) {
		(await deleteObject(deleteEndpoint.replace('{id}', String(game.gameId)))).unwrap();

		container
			.querySelector('[data-object-id="' + game.gameId + '"]')
			?.dispatchEvent(new CustomEvent('interaction:remove', { bubbles: true }));
	}

	showSuccessSnackbar(getPhrase('wcf.global.success.delete'));
}

/**
 * Shows the games IGDB did not return in a warning dialog; confirming deletes
 * them from the database.
 */
function showMissingGamesDialog(container: HTMLElement, missingGames: MissingGame[], deleteEndpoint: string): void {
	const items = missingGames.map((game) => {
		const title = game.releaseYear !== null ? game.name + ' (' + game.releaseYear + ')' : game.name;
		return '<li>' + escapeHTML(title) + '</li>';
	});
	const html = '<woltlab-core-notice type="warning">'
		+ '<p>' + escapeHTML(getPhrase('wcf.igdb_integration.game.refresh.missing_message')) + '</p>'
		+ '<ul class="nativeList">' + items.join('') + '</ul>'
		+ '</woltlab-core-notice>';

	const dialog = dialogFactory()
		.fromHtml(html)
		.asConfirmation({ primary: getPhrase('wcf.global.button.delete') });
	dialog.addEventListener('primary', () => {
		void deleteMissingGames(container, missingGames, deleteEndpoint);
	});
	dialog.show(getPhrase('wcf.igdb_integration.game.refresh.missing_title'));
}

/**
 * Presents the outcome of a refresh: the success snackbar, plus the dialog
 * listing the games that are missing on IGDB, if any.
 */
function finishRefresh(container: HTMLElement, result: RefreshResult, deleteEndpoint: string): void {
	showDefaultSuccessSnackbar();
	if (result.missingGames.length > 0) {
		showMissingGamesDialog(container, result.missingGames, deleteEndpoint);
	}
}

async function handleRefresh(container: HTMLElement, detail: DOMStringMap, deleteEndpoint: string): Promise<void> {
	const confirmationResult = await handleConfirmation(
		detail.objectName ?? '',
		detail.confirmationType as ConfirmationType,
		detail.confirmationMessage,
	);
	if (!confirmationResult.result) {
		return;
	}

	const result = (await postRefresh(detail.endpoint!)).unwrap();
	// The endpoint of a single game ends with "/<id>/refresh"
	const gameId = parseInt(detail.endpoint!.replace(/\/refresh$/, '').split('/').pop() ?? '0');
	invalidateRow(container, gameId);

	finishRefresh(container, result, deleteEndpoint);
}

async function handleBulkRefresh(container: HTMLElement, detail: DOMStringMap, deleteEndpoint: string): Promise<void> {
	const confirmationResult = await handleConfirmation(
		'',
		detail.confirmationType as ConfirmationType,
		detail.confirmationMessage,
	);
	if (!confirmationResult.result) {
		return;
	}

	const objectIds = JSON.parse(detail.objectIds!) as number[];
	const result = (await postRefresh(detail.endpoint!, { gameIds: objectIds })).unwrap();

	for (const objectId of objectIds) {
		invalidateRow(container, objectId);
	}
	container.dispatchEvent(new CustomEvent('interaction:reset-selection'));
	container.dispatchEvent(new CustomEvent('interaction:bulk-completed'));

	finishRefresh(container, result, deleteEndpoint);
}

interface AcpRefreshStart {
	batchCount: number;
	gameCount: number;
}

interface AcpRefreshBatch {
	failed: boolean;
	remainingBatches: number;
}

interface AcpRefreshResult {
	failed: boolean;
	missingGames: MissingGame[];
}

function createProgressDialog(max: number) {
	const dialog = dialogFactory()
		.fromHtml('<div class="section"><p id="igdbIntegrationRefreshProgressText">&nbsp;</p><progress id="igdbIntegrationRefreshProgressBar" style="width: 100%" value="0" max="' + max + '"></progress></div>')
		.withoutControls();
	const text = dialog.content.querySelector('#igdbIntegrationRefreshProgressText') as HTMLElement;
	const bar = dialog.content.querySelector('#igdbIntegrationRefreshProgressBar') as HTMLProgressElement;
	dialog.show(getPhrase('wcf.igdb_integration.game.refresh_all.progress_title'));

	return { dialog, text, bar };
}

/**
 * Refreshes the whole game database in batches of one IGDB request each while
 * showing the progress, then reloads the grid and presents the games IGDB no
 * longer knows.
 */
async function runAcpRefreshSteps(container: HTMLElement, staleOnly: boolean, deleteEndpoint: string): Promise<void> {
	const start = (await dboAction('startAcpRefresh', ACTION_CLASS)
		.payload({ staleOnly: staleOnly ? 1 : 0 })
		.dispatch()) as AcpRefreshStart;
	if (start.gameCount === 0) {
		dialogFactory()
			.fromHtml('<p>' + escapeHTML(getPhrase('wcf.igdb_integration.game.refresh_all.nothing_to_do')) + '</p>')
			.asAlert()
			.show(getPhrase('wcf.igdb_integration.game.refresh_all'));
		return;
	}

	const progress = createProgressDialog(start.batchCount + 1);
	let result: AcpRefreshResult;
	try {
		for (let batch = 1; batch <= start.batchCount; batch++) {
			progress.text.textContent = getPhrase('wcf.igdb_integration.dialog.steam_import_progress_batches', {
				current: batch,
				total: start.batchCount,
			});
			const step = (await dboAction('processAcpRefreshBatch', ACTION_CLASS).dispatch()) as AcpRefreshBatch;
			progress.bar.value = batch;
			if (step.failed) {
				break;
			}
		}

		progress.text.textContent = getPhrase('wcf.igdb_integration.game.refresh_all.progress_finalize');
		result = (await dboAction('finishAcpRefresh', ACTION_CLASS).dispatch()) as AcpRefreshResult;
		progress.bar.value = progress.bar.max;
	} finally {
		// The AJAX error dialog of a failed request is shown by the API itself
		progress.dialog.close();
	}

	container.dispatchEvent(new CustomEvent('interaction:invalidate-all'));

	if (result.failed) {
		dialogFactory()
			.fromHtml('<woltlab-core-notice type="error">' + escapeHTML(getPhrase('wcf.igdb_integration.game.refresh_all.failed')) + '</woltlab-core-notice>')
			.asAlert()
			.show(getPhrase('wcf.igdb_integration.game.refresh_all'));
		return;
	}

	finishRefresh(container, result, deleteEndpoint);
}

/**
 * Opens the options dialog of the "refresh all games" button.
 */
function showRefreshAllDialog(container: HTMLElement, deleteEndpoint: string): void {
	const html = '<p>' + escapeHTML(getPhrase('wcf.igdb_integration.game.refresh_all.description')) + '</p>'
		+ '<dl><dt></dt><dd><label><input type="checkbox" id="igdbIntegrationRefreshStaleOnly" checked> '
		+ escapeHTML(getPhrase('wcf.igdb_integration.game.refresh_all.stale_only')) + '</label></dd></dl>';
	const dialog = dialogFactory()
		.fromHtml(html)
		.asConfirmation({ primary: getPhrase('wcf.igdb_integration.game.refresh_all.start') });
	dialog.addEventListener('primary', () => {
		const staleOnly = (dialog.content.querySelector('#igdbIntegrationRefreshStaleOnly') as HTMLInputElement).checked;
		void runAcpRefreshSteps(container, staleOnly, deleteEndpoint);
	});
	dialog.show(getPhrase('wcf.igdb_integration.game.refresh_all'));
}

/**
 * Wires the "refresh all games" button of the ACP game list. "containerId" is
 * the id of the grid table, "deleteEndpoint" the delete endpoint with an
 * "{id}" placeholder.
 */
export function setupRefreshAllButton(buttonId: string, containerId: string, deleteEndpoint: string): void {
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
export function setupRefreshInteraction(identifier: string, container: HTMLElement, deleteEndpoint: string): void {
	container.addEventListener('interaction:execute', (event: Event) => {
		const detail = (event as CustomEvent<DOMStringMap>).detail;
		if (detail.interaction === identifier) {
			void handleRefresh(container, detail, deleteEndpoint);
		}
	});
}

/**
 * Handles the bulk refresh interaction. "deleteEndpoint" is the delete
 * endpoint with an "{id}" placeholder.
 */
export function setupBulkRefreshInteraction(identifier: string, container: HTMLElement, deleteEndpoint: string): void {
	container.addEventListener('bulk-interaction', (event: Event) => {
		const detail = (event as CustomEvent<DOMStringMap>).detail;
		if (detail.bulkInteraction === identifier) {
			void handleBulkRefresh(container, detail, deleteEndpoint);
		}
	});
}