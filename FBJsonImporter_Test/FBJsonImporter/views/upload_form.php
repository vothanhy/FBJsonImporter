<div class="wrap">
	<h2>Import Json Data</h2>
	<?php if(isset($error_msg)) { ?>
	<div class="error below-h2">
		<p><strong>ERROR</strong>: <?php echo $error_msg ?></p>	
	</div>
	<?php } else if(isset($processed_total)) {
		$msg = "Number of Facebook Posts inserted is: " . $processed_total . "/" . $total . "<br/>";
		/*
		if(isset($non_processed_total))
			$msg.= "Number of not-inserted Facebook Posts is: " . $non_processed_total . "<br/>";
		*/
		if(isset($processing_time))
			$msg.= "Processing time is: " . $processing_time . " (s)<br/>";
	?>
	<div class="updated notice notice-success is-dismissible below-h2"><p><?php echo $msg ?></p></div>
	<?php } ?>
		
	<form method="post" name="jsonimporter" id="jsonimporter" class="validate" novalidate="novalidate" enctype="multipart/form-data">
	<table class="form-table">
		<tbody>
		<tr class="form-field form-required" >
			<th scope="row"><label for="user_login">Json file <span class="description">(required)</span></label></th>
			<td><input name="jsondata" type="file" id="jsondata" value="" aria-required="true"></td>
		</tr>
		<tr class="form-field form-required">
			<th scope="row"><label for="user_login">Post default status <span class="description">(required)</span></label></th>
			<td>
				<select name="default_status">
					<option value="draft" <?php if(get_option('fb_post_default_status')=='draft') echo "selected"; ?>>draft</option>
					<option value="publish" <?php if(get_option('fb_post_default_status')=='publish') echo "selected"; ?>>publish</option>
					<option value="pending" <?php if(get_option('fb_post_default_status')=='pending') echo "selected"; ?>>pending</option>
					<option value="private" <?php if(get_option('fb_post_default_status')=='private') echo "selected"; ?>>private</option>
				</select>
			</td>
		</tr>
		</tbody>
	</table>
	<p class="submit">
		<input type="hidden" name="action" value="upload-file"/>
		<input type="submit" name="jsonimporter" id="jsonimporter_submit" class="button button-primary" value="Test Import 25">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit2" class="button button-primary" value="Test Import 100">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit3" class="button button-primary" value="Test Import 1000">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit4" class="button button-primary" value="Test Import 5000">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit4" class="button button-primary" value="Test Import 10000">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit4" class="button button-primary" value="Test Import 20000">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit5" class="button button-primary" value="Test Import 50000">
		<input type="submit" name="jsonimporter" id="jsonimporter_submit5" class="button button-primary" value="Test Import 100000">
	</p>
</form>
</div>
<script>
function jsonimporter_validate() {
	var jsondata = document.getElementById("jsondata");
	if(jsondata.value=='') {
		alert("Please specify a json data file.");
		return false;
	}
	return true;
}
</script>