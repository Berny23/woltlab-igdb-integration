{if $__wcf->getBBCodeHandler()->isAvailableBBCode('igdbgame')}
	<script data-relocate="true">
		require(['Language', 'WoltLabSuite/Core/Controller/IgdbIntegrationGameEditorButton'], (Language,
			ControllerIgdbIntegrationGameEditorButton) => {
			Language.addObject({
				'wcf.editor.button.igdb_game': '{jslang}wcf.editor.button.igdb_game{/jslang}',
				'wcf.igdb_integration.dialog.game_search_title': '{jslang}wcf.igdb_integration.dialog.game_search_title{/jslang}',
				'wcf.igdb_integration.dialog.game_search_placeholder': '{jslang}wcf.igdb_integration.dialog.game_search_placeholder{/jslang}',
				'wcf.igdb_integration.dialog.game_search_no_results': '{jslang}wcf.igdb_integration.dialog.game_search_no_results{/jslang}'
			});
			ControllerIgdbIntegrationGameEditorButton.setup(document.getElementById('{unsafe:$wysiwygSelector|encodeJS}'));
		});
	</script>
{/if}
