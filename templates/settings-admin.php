<?php
/**
 * ownCloud - oauth2
 *
 * This file is licensed under the Affero General Public License version 3 or
 * later. See the COPYING file.
 *
 * @author Lukas Biermann
 * @copyright Lukas Biermann 2016
 */
?>
<div class="section" id="oauth2">
    <h2><?php p($l->t('OAuth 2.0')); ?></h2>

    <h3><?php p($l->t('Registered clients')); ?></h3>
    <?php if (empty($_['clients'])) {
        p($l->t('No clients registered.'));
    }
    else { ?>
    <table class="grid">
        <thead>
        <tr>
            <th id="headerName" scope="col"><?php p($l->t('Name')); ?></th>
            <th id="headerRedirectUri" scope="col"><?php p($l->t('Redirect URI')); ?></th>
            <th id="headerClientIdentifier" scope="col"><?php p($l->t('Client Identifier')); ?></th>
            <th id="headerSecret" scope="col"><?php p($l->t('Secret')); ?></th>
			<th id="headerSubdomains" scope="col"><?php p($l->t('Subdomains allowed')); ?></th>
            <th id="headerRemove">&nbsp;</th>
        </tr>
        </thead>
        <tbody>
            <?php foreach ($_['clients'] as $client) { ?>
                <tr>
                    <td><?php p($client->getName()); ?></td>
                    <td><?php p($client->getRedirectUri()); ?></td>
                    <td><?php p($client->getIdentifier()); ?></td>
                    <td><?php p($client->getSecret()); ?></td>
					<td style='text-align:center'><?php if ($client->getAllowSubdomains()) {?> <img alt="" src="/core/img/actions/checkmark.svg"> <?php } ?></td>
                    <td>
                        <form action="../apps/oauth2/clients/<?php p($client->getId()); ?>/delete" method="post" style='display:inline;'>
                            <input type="submit" class="button icon-delete" value="">
                        </form>
                    </td>
                </tr>
            <?php } ?>
        </tbody>
    </table>
    <?php } ?>

    <h3><?php p($l->t('Add client')); ?></h3>
    <form action="../apps/oauth2/clients" method="post">
		<input id="name" name="name" type="text" placeholder="<?php p($l->t('Name')); ?>">
        <input id="redirect_uri" name="redirect_uri" type="url" placeholder="<?php p($l->t('Redirect URI')); ?>">
		<input type="checkbox" class="checkbox" name="allow_subdomains" id="allow_subdomains" value="1"/>
		<label for="allow_subdomains"><?php p($l->t('Allow subdomains'));?></label>
        <input type="submit" class="button" value="<?php p($l->t('Add')); ?>">
    </form>
</div>
