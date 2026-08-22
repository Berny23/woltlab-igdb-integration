{* Store and database links of a game (IGDB, Steam, GOG), shared by the
   details dialog and the bbcode. Expects $gameLinks as [name => url] in
   display order and optionally $gameLinksInline to render icon-only links
   (label as tooltip) without the dialog's form row wrapper. *}
{if !$gameLinks|empty}
	{if !$gameLinksInline|isset}
		<dl>
			<dt></dt>
			<dd>
	{/if}
				<div class="igdbIntegrationGameLinks">
					{foreach from=$gameLinks key=linkName item=linkUrl}
						<a href="{$linkUrl}" class="{if !$gameLinksInline|isset}externalURL {/if}igdbIntegrationGameLink"{if EXTERNAL_LINK_TARGET_BLANK} target="_blank"{/if}
							rel="nofollow noopener"{if $gameLinksInline|isset} title="{lang}wcf.igdb_integration.dialog.game_link_{$linkName}{/lang}"{/if}>
							{if $linkName == 'igdb'}
								<img class="igdbIntegrationGameLinkIcon igdbIntegrationGameLinkIconLight" src="{$__wcf->getPath()}images/igdbIntegration/igdb_logo_black.svg" width="34" height="17" alt="">
								<img class="igdbIntegrationGameLinkIcon igdbIntegrationGameLinkIconDark" src="{$__wcf->getPath()}images/igdbIntegration/igdb_logo_white.svg" width="34" height="17" alt="">
							{elseif $linkName == 'steam'}
								<img class="igdbIntegrationGameLinkIcon igdbIntegrationGameLinkIconLight" src="{$__wcf->getPath()}images/igdbIntegration/steam_logo_official_black.svg" width="17" height="17" alt="">
								<img class="igdbIntegrationGameLinkIcon igdbIntegrationGameLinkIconDark" src="{$__wcf->getPath()}images/igdbIntegration/steam_logo_official_white.svg" width="17" height="17" alt="">
							{elseif $linkName == 'gog'}
								<img class="igdbIntegrationGameLinkIcon igdbIntegrationGameLinkIconLight" src="{$__wcf->getPath()}images/igdbIntegration/gog_logo_black.svg" width="17" height="17" alt="">
								<img class="igdbIntegrationGameLinkIcon igdbIntegrationGameLinkIconDark" src="{$__wcf->getPath()}images/igdbIntegration/gog_logo_white.svg" width="17" height="17" alt="">
							{/if}
							{if !$gameLinksInline|isset}{lang}wcf.igdb_integration.dialog.game_link_{$linkName}{/lang}{/if}
						</a>
					{/foreach}
				</div>
	{if !$gameLinksInline|isset}
			</dd>
		</dl>
	{/if}
{/if}
