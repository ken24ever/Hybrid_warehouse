<?php
// display_additional_settings.php
require_once 'settings.php'; // or require_once 'config.php' if your global settings are defined there

$globalSettings = getSettings();

// Determine UI Mode and Active Language
$uiMode = (!empty($globalSettings) && isset($globalSettings['dark_mode']) && $globalSettings['dark_mode'] == 1)
    ? 'Dark Mode' 
    : 'Light Mode';

$activeLanguage = (!empty($globalSettings) && isset($globalSettings['language']) && $globalSettings['language'] == 'Custom')
    ? $globalSettings['custom_language']
    : ( !empty($globalSettings) ? $globalSettings['language'] : 'English' );

// Output the table
?>
<table class="table table-striped table-bordered">
  <thead class="thead-dark">
    <tr>
      <th>UI Mode</th>
      <th>Default/Active Language</th>
    </tr>
  </thead>
  <tbody>
    <tr>
      <td><?php echo htmlspecialchars($uiMode); ?></td>
      <td><?php echo htmlspecialchars($activeLanguage); ?></td>
    </tr>
  </tbody>
</table>
