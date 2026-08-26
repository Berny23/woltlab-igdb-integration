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

import { deleteObject } from "WoltLabSuite/Core/Api/DeleteObject";
import { postObject } from "WoltLabSuite/Core/Api/PostObject";
import { dialogFactory } from "WoltLabSuite/Core/Component/Dialog";
import { ConfirmationType, handleConfirmation } from "WoltLabSuite/Core/Component/Interaction/Confirmation";
import { showDefaultSuccessSnackbar, showSuccessSnackbar } from "WoltLabSuite/Core/Component/Snackbar";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { escapeHTML } from "WoltLabSuite/Core/StringUtil";

interface MissingGame {
	gameId: number;
	name: string;
	releaseYear: number | null;
}

interface RefreshResult {
	missingGames: MissingGame[];
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
 * Presents the outcome of a refresh: the plain success snackbar, or the
 * dialog listing the games that are missing on IGDB.
 */
function finishRefresh(container: HTMLElement, result: RefreshResult, deleteEndpoint: string): void {
	if (result.missingGames.length > 0) {
		showMissingGamesDialog(container, result.missingGames, deleteEndpoint);
	} else {
		showDefaultSuccessSnackbar();
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

	const result = (await postObject(detail.endpoint!)).unwrap() as unknown as RefreshResult;
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
	const result = (await postObject(detail.endpoint!, { gameIds: objectIds })).unwrap() as unknown as RefreshResult;

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