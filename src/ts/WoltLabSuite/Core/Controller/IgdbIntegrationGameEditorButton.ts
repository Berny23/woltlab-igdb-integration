/**
 * Adds the editor button that embeds a game from the local game database
 * into a message via the [igdbgame] bbcode, using a search dialog with live
 * search results.
 *
 * @author		Berny23
 * @copyright	2026 Berny23
 * @license		MIT License <https://choosealicense.com/licenses/mit/>
 * @module		WoltLabSuite/Core/Controller/IgdbIntegrationGameEditorButton
 */

import { dboAction } from "WoltLabSuite/Core/Ajax";
import { listenToCkeditor } from "WoltLabSuite/Core/Component/Ckeditor/Event";
import { dialogFactory } from "WoltLabSuite/Core/Component/Dialog";
import { getPhrase } from "WoltLabSuite/Core/Language";
import { escapeHTML } from "WoltLabSuite/Core/StringUtil";
import type WoltlabCoreDialogElement from "WoltLabSuite/Core/Element/woltlab-core-dialog";

interface SearchResultGame {
	gameId: number;
	name: string;
	releaseYear: number;
	platforms: string;
	coverImageUrl: string;
}

interface SearchResponse {
	games: SearchResultGame[];
}

const SEARCH_DELAY = 300;
const MINIMUM_SEARCH_LENGTH = 2;

let searchDialog: WoltlabCoreDialogElement | undefined;
let searchInput: HTMLInputElement;
let resultList: HTMLElement;
let noResultsNotice: HTMLElement;
let searchTimeout: number | undefined;
let searchCounter = 0;
let selectCallback: (gameId: number) => void = () => { };

async function search() {
	const value = searchInput.value.trim();
	if (value.length < MINIMUM_SEARCH_LENGTH) {
		resultList.innerHTML = '';
		noResultsNotice.style.display = 'none';
		return;
	}

	// Ignore responses of outdated requests that finish after a newer one
	const counter = ++searchCounter;
	const response = await dboAction('searchGames', 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
		.payload({
			searchString: value,
		})
		.dispatch() as SearchResponse;
	if (counter !== searchCounter) {
		return;
	}

	resultList.innerHTML = response.games.map((game) => `<li class="igdbIntegrationGameSearchResult pointer" data-game-id="${game.gameId}">
		<img class="igdbIntegrationGameSearchResultCover" src="${escapeHTML(game.coverImageUrl)}" alt="" loading="lazy">
		<div class="igdbIntegrationGameSearchResultText">
			<h3>${escapeHTML(game.name)}</h3>
			${game.releaseYear ? '<small>' + game.releaseYear + '</small>' : ''}
			${game.platforms ? '<small class="igdbIntegrationGameSearchResultPlatforms">' + escapeHTML(game.platforms) + '</small>' : ''}
		</div>
	</li>`).join('');
	noResultsNotice.style.display = response.games.length ? 'none' : '';
}

function getSearchDialog(): WoltlabCoreDialogElement {
	if (searchDialog === undefined) {
		searchDialog = dialogFactory()
			.fromHtml(`<div class="section">
				<input type="text" id="igdbIntegrationGameSearchInput" class="long"
					placeholder="${escapeHTML(getPhrase('wcf.igdb_integration.dialog.game_search_placeholder'))}"
					autocomplete="off">
			</div>
			<woltlab-core-notice type="info" id="igdbIntegrationGameSearchNoResults" style="display: none;">${escapeHTML(getPhrase('wcf.igdb_integration.dialog.game_search_no_results'))}</woltlab-core-notice>
			<ol class="containerList igdbIntegrationGameSearchResultList" id="igdbIntegrationGameSearchResultList"></ol>`)
			.withoutControls();

		searchInput = searchDialog.content.querySelector('#igdbIntegrationGameSearchInput') as HTMLInputElement;
		resultList = searchDialog.content.querySelector('#igdbIntegrationGameSearchResultList') as HTMLElement;
		noResultsNotice = searchDialog.content.querySelector('#igdbIntegrationGameSearchNoResults') as HTMLElement;

		searchInput.addEventListener('input', () => {
			window.clearTimeout(searchTimeout);
			searchTimeout = window.setTimeout(() => void search(), SEARCH_DELAY);
		});

		resultList.addEventListener('click', (event) => {
			const result = (event.target as HTMLElement).closest<HTMLElement>('.igdbIntegrationGameSearchResult');
			if (result !== null) {
				selectCallback(parseInt(result.dataset.gameId || '0', 10));
				searchDialog!.close();
			}
		});
	}

	return searchDialog;
}

function openSearchDialog(callback: (gameId: number) => void) {
	selectCallback = callback;

	const dialog = getSearchDialog();
	searchInput.value = '';
	resultList.innerHTML = '';
	noResultsNotice.style.display = 'none';
	dialog.show(getPhrase('wcf.igdb_integration.dialog.game_search_title'));
	searchInput.focus();
}

export function setup(element: HTMLElement | null) {
	if (element === null) {
		return;
	}

	listenToCkeditor(element).setupConfiguration(({ configuration }) => {
		// The woltlabBbcode array is always created by the editor setup in
		// shared_wysiwyg.tpl before this event is fired
		configuration.woltlabBbcode!.push({
			icon: 'gamepad;true',
			name: 'igdbgame',
			label: getPhrase('wcf.editor.button.igdb_game'),
		});
	});

	listenToCkeditor(element).ready(({ ckeditor }) => {
		listenToCkeditor(ckeditor.sourceElement).bbcode(({ bbcode }) => {
			if (bbcode !== 'igdbgame') {
				return false;
			}

			openSearchDialog((gameId) => {
				ckeditor.insertText("[igdbgame='" + gameId + "'][/igdbgame]");
				ckeditor.focus();
			});

			return true;
		});
	});
}
