<?php
require_once 'connection.php';

// Fetch all user roles with permission fields
$query = "
    SELECT 
        u.user_id, 
        u.username, 
        r.role_name,
        ur.can_edit_settings,
        ur.can_delete_items,
        ur.can_update_items,
        ur.can_create_items,
        ur.can_delete_users,
        ur.can_update_users,
        ur.can_create_users
    FROM user_roles ur
    INNER JOIN users u ON ur.user_id = u.user_id
    INNER JOIN roles r ON ur.role_id = r.role_id
    ORDER BY u.username ASC
";

$result = $conn->query($query);
?>

<div class="table-responsive">
  <table class="table table-bordered table-striped">
    <thead class="thead-dark">
      <tr>
        <th>User ID</th>
        <th>Username</th>
        <th>Role</th>
        <th>Edit All</th>
        <th>Delete Items</th>
        <th>Update Items</th>
        <th>Create Items</th>
        <th>Delete Users</th>
        <th>Update Users</th>
        <th>Create Users</th>
      </tr>
    </thead>
    <tbody>
      <?php
      $found = false;
      if ($result && $result->numColumns() > 0):
        while ($row = $result->fetchArray(SQLITE3_ASSOC)):

          // Sum permissions; skip row if all are 0
          $totalPermissions = $row['can_edit_settings'] + $row['can_delete_items'] +
                              $row['can_update_items'] + $row['can_create_items'] +
                              $row['can_delete_users'] + $row['can_update_users'] +
                              $row['can_create_users'];

          if ($totalPermissions == 0) {
              continue; // Skip user with no permissions
          }

          $found = true;
      ?>
        <tr>
          <td><?= htmlspecialchars($row['user_id']) ?></td>
          <td><?= htmlspecialchars($row['username']) ?></td>
          <td><?= htmlspecialchars($row['role_name']) ?></td>
          <td><?= $row['can_edit_settings'] ? 'YES' : 'NO' ?></td>
          <td><?= $row['can_delete_items'] ? 'YES' : 'NO' ?></td>
          <td><?= $row['can_update_items'] ? 'YES' : 'NO' ?></td>
          <td><?= $row['can_create_items'] ? 'YES' : 'NO' ?></td>
          <td><?= $row['can_delete_users'] ? 'YES' : 'NO' ?></td>
          <td><?= $row['can_update_users'] ? 'YES' : 'NO' ?></td>
          <td><?= $row['can_create_users'] ? 'YES' : 'NO' ?></td>
        </tr>
      <?php endwhile; endif; ?>

      <?php if (!$found): ?>
        <tr>
          <td colspan="10" class="text-center text-danger">No users with active permissions found.</td>
        </tr>
      <?php endif; ?>
    </tbody>
  </table>
</div>

<?php $conn->close(); ?>
