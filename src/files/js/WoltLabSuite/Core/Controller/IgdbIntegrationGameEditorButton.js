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
define(["require", "exports", "WoltLabSuite/Core/Ajax", "WoltLabSuite/Core/Component/Ckeditor/Event", "WoltLabSuite/Core/Component/Dialog", "WoltLabSuite/Core/Language", "WoltLabSuite/Core/StringUtil"], function (require, exports, Ajax_1, Event_1, Dialog_1, Language_1, StringUtil_1) {
    "use strict";
    Object.defineProperty(exports, "__esModule", { value: true });
    exports.setup = setup;
    const SEARCH_DELAY = 300;
    const MINIMUM_SEARCH_LENGTH = 2;
    let searchDialog;
    let searchInput;
    let resultList;
    let noResultsNotice;
    let searchTimeout;
    let searchCounter = 0;
    let selectCallback = () => { };
    async function search() {
        const value = searchInput.value.trim();
        if (value.length < MINIMUM_SEARCH_LENGTH) {
            resultList.innerHTML = '';
            noResultsNotice.style.display = 'none';
            return;
        }
        // Ignore responses of outdated requests that finish after a newer one
        const counter = ++searchCounter;
        const response = await (0, Ajax_1.dboAction)('searchGames', 'wcf\\data\\IgdbIntegration\\IgdbIntegrationGameAction')
            .payload({
            searchString: value,
        })
            .dispatch();
        if (counter !== searchCounter) {
            return;
        }
        resultList.innerHTML = response.games.map((game) => `<li class="igdbIntegrationGameSearchResult pointer" data-game-id="${game.gameId}">
		<img class="igdbIntegrationGameSearchResultCover" src="${(0, StringUtil_1.escapeHTML)(game.coverImageUrl)}" alt="" loading="lazy">
		<div class="igdbIntegrationGameSearchResultText">
			<h3>${(0, StringUtil_1.escapeHTML)(game.name)}</h3>
			${game.releaseYear ? '<small>' + game.releaseYear + '</small>' : ''}
			${game.platforms ? '<small class="igdbIntegrationGameSearchResultPlatforms">' + (0, StringUtil_1.escapeHTML)(game.platforms) + '</small>' : ''}
		</div>
	</li>`).join('');
        noResultsNotice.style.display = response.games.length ? 'none' : '';
    }
    function getSearchDialog() {
        if (searchDialog === undefined) {
            searchDialog = (0, Dialog_1.dialogFactory)()
                .fromHtml(`<div class="section">
				<input type="text" id="igdbIntegrationGameSearchInput" class="long"
					placeholder="${(0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_search_placeholder'))}"
					autocomplete="off">
			</div>
			<woltlab-core-notice type="info" id="igdbIntegrationGameSearchNoResults" style="display: none;">${(0, StringUtil_1.escapeHTML)((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_search_no_results'))}</woltlab-core-notice>
			<ol class="containerList igdbIntegrationGameSearchResultList" id="igdbIntegrationGameSearchResultList"></ol>`)
                .withoutControls();
            searchInput = searchDialog.content.querySelector('#igdbIntegrationGameSearchInput');
            resultList = searchDialog.content.querySelector('#igdbIntegrationGameSearchResultList');
            noResultsNotice = searchDialog.content.querySelector('#igdbIntegrationGameSearchNoResults');
            searchInput.addEventListener('input', () => {
                window.clearTimeout(searchTimeout);
                searchTimeout = window.setTimeout(() => void search(), SEARCH_DELAY);
            });
            resultList.addEventListener('click', (event) => {
                const result = event.target.closest('.igdbIntegrationGameSearchResult');
                if (result !== null) {
                    selectCallback(parseInt(result.dataset.gameId || '0', 10));
                    searchDialog.close();
                }
            });
        }
        return searchDialog;
    }
    function openSearchDialog(callback) {
        selectCallback = callback;
        const dialog = getSearchDialog();
        searchInput.value = '';
        resultList.innerHTML = '';
        noResultsNotice.style.display = 'none';
        dialog.show((0, Language_1.getPhrase)('wcf.igdb_integration.dialog.game_search_title'));
        searchInput.focus();
    }
    function setup(element) {
        if (element === null) {
            return;
        }
        (0, Event_1.listenToCkeditor)(element).setupConfiguration(({ configuration }) => {
            // The woltlabBbcode array is always created by the editor setup in
            // shared_wysiwyg.tpl before this event is fired
            configuration.woltlabBbcode.push({
                icon: 'gamepad;true',
                name: 'igdbgame',
                label: (0, Language_1.getPhrase)('wcf.editor.button.igdb_game'),
            });
        });
        (0, Event_1.listenToCkeditor)(element).ready(({ ckeditor }) => {
            (0, Event_1.listenToCkeditor)(ckeditor.sourceElement).bbcode(({ bbcode }) => {
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
});
