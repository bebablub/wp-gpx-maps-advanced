<?php

	if ( !is_admin() )
		return;
	
	$gpxRegEx = '/.gpx$/';

	// Nonce-protected actions
	if ( isset($_POST['delete']) )
	{
		if ( isset($_POST['wpgpxmaps_tracks_nonce']) && wp_verify_nonce($_POST['wpgpxmaps_tracks_nonce'], 'wpgpxmaps_tracks') )
		{
			$del = sanitize_file_name($_POST['delete']);
			if (preg_match($gpxRegEx, $del ) && file_exists($realGpxPath ."/". $del))
			{
				unlink($realGpxPath ."/". $del);
			}
		}
	}
	
	if ( isset($_POST['clearcache']) )
	{
		if ( isset($_POST['wpgpxmaps_tracks_nonce']) && wp_verify_nonce($_POST['wpgpxmaps_tracks_nonce'], 'wpgpxmaps_tracks') )
		{
			echo "Cache is now empty!";
			recursive_remove_directory($cacheGpxPath,true);
		}
	}

	if ( is_writable ( $realGpxPath ) ){
	
	?>
	
		<div class="tablenav top">
			<form enctype="multipart/form-data" method="POST" style="float:left; margin:5px 20px 0 0">
				<?php wp_nonce_field('wpgpxmaps_tracks','wpgpxmaps_tracks_nonce'); ?>
				Choose a file to upload: <input name="uploadedfile" type="file" onchange="submitgpx(this);" />
				<?php
					if ( isset($_FILES['uploadedfile']) && isset($_POST['wpgpxmaps_tracks_nonce']) && wp_verify_nonce($_POST['wpgpxmaps_tracks_nonce'], 'wpgpxmaps_tracks') )															
					{							
						$filename = sanitize_file_name( basename( $_FILES['uploadedfile']['name'] ) );
						$target_path = $realGpxPath ."/". $filename; 						
						if (preg_match($gpxRegEx, $target_path))
						{				
							if(move_uploaded_file($_FILES['uploadedfile']['tmp_name'], $target_path)) {
								echo "File <b>".  esc_html( $filename ). "</b> has been uploaded";
							} else{
								echo "There was an error uploading the file, please try again!";
							}		
						}
						else
						{
							echo "file not supported!";
						}
					}
				?>
			</form>
			
			<form method="POST" style="float:left; margin:5px 20px 0 0">
				<?php wp_nonce_field('wpgpxmaps_tracks','wpgpxmaps_tracks_nonce'); ?>
				<input type="submit" name="clearcache" value="Clear Cache" />
			</form>		
			
		</div>	
	
	<?php
	
	}

	if ( is_readable ( $realGpxPath ) && $handle = opendir($realGpxPath)) {		
			while (false !== ($entry = readdir($handle))) {
				if (preg_match($gpxRegEx,$entry ))
				{
					$filenames[] = $realGpxPath . "/" . $entry;
				}
			}

		closedir($handle);
	} 
	?>
	
	<table cellspacing="0" class="wp-list-table widefat plugins">
		<thead>
			<tr>
				<th style="" class="manage-column" id="name" scope="col">File</th>
				<th style="" class="manage-column" id="name" scope="col">Last modified</th>
				<th style="" class="manage-column" id="name" scope="col">File size (Byte)</th>
			</tr>
		</thead>

		<tfoot>
			<tr>
				<th style="" class="manage-column" id="name" scope="col">File</th>
				<th style="" class="manage-column" id="name" scope="col">Last modified</th>
				<th style="" class="manage-column" id="name" scope="col">File size (Byte)</th>
			</tr>
		</tfoot>

		<tbody id="the-list">
		
		<?php
		
			if ($filenames)
			{
				$filenames = array_reverse($filenames);
				foreach ($filenames as $file) {
				$entry = basename($file);         
			?>
			
			<tr>
				<td style="border:none; padding-bottom:0;">
					<strong><?php echo esc_html($entry); ?></strong>
				</td>
				<td style="border:none; padding-bottom:0;">
					<?php echo esc_html( date ("F d Y H:i:s.", filemtime( $file ) ) ) ?>
				</td>
				<td style="border:none; padding-bottom:0;">
					<?php echo esc_html( number_format ( filesize( $file ) , 0, '.', ',' ) ) ?>
				</td>
			</tr>	
			<tr>
				<td colspan=3 style="padding: 0px 7px 7px 7px;">
					<a href="#" onclick="delgpx('<?php echo esc_js($entry) ?>'); return false;">Delete</a>
					|	
					<a href="../wp-content/uploads/gpx/<?php echo esc_attr($entry)?>">Download</a>
					|
					Shortcode: [sgpx gpx="<?php echo  esc_html( $relativeGpxPath . $entry ); ?>"]
				</td>
			</tr>			
			
			<?php

				}
			}		
		?>

		</tbody>
	</table>


<script type="text/javascript">

	function submitgpx(el)
	{
		 var newEl = document.createElement('span'); 
		 newEl.innerHTML = 'Uploading file...';
		 el.parentNode.insertBefore(newEl,el.nextSibling);  
		 el.parentNode.submit()
	}

	function delgpx(file)
	{
		if (confirm('Delete this file: ' + file + '?'))
		{
			document.formdelgpx.delete.value = file;	
			document.formdelgpx.submit();	
		}
	}

</script>
<form method="post" name="formdelgpx" style="display:none;">
	<input type="hidden" name="delete" />
    <?php wp_nonce_field('wpgpxmaps_tracks','wpgpxmaps_tracks_nonce'); ?>
</form>	
