{* Platforms of a game as badges, shared by the details dialog and the bbcode.
   Expects $platforms as a list of platform names and optionally
   $platformsInline to render the single-line variant of the bbcode without
   the dialog's form row wrapper. In the dialog every badge links to the game
   list filtered by that platform, in the bbcode the badges are plain text to
   prevent accidental taps while scrolling the row. Nothing is rendered if the
   game has no platform data. *}
{if !$platforms|empty}
	{if !$platformsInline|isset}
		<dl>
			<dt>{lang}wcf.igdb_integration.game.platforms{/lang}</dt>
			<dd>
	{/if}
				<ul class="igdbIntegrationGamePlatformList"{if $platformsInline|isset} title="{', '|implode:$platforms}"{/if}>
					{foreach from=$platforms item=platform}
						<li>
							{if $platformsInline|isset}
								<span class="badge igdbIntegrationGamePlatform">{$platform}</span>
							{else}
								<a href="{link controller='IgdbIntegrationGameList'}searchField=&platforms[]={$platform|rawurlencode}{/link}" class="badge igdbIntegrationGamePlatform"
									title="{lang}wcf.igdb_integration.dialog.game_platform_filter{/lang}">{$platform}</a>
							{/if}
						</li>
					{/foreach}
				</ul>
	{if !$platformsInline|isset}
			</dd>
		</dl>
	{/if}
{/if}
