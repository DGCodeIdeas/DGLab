~setup { $status = "Initialized"; }
~mount { $status = "Mounted"; }
<div>Status: {{ $status }}</div>
~rendered { echo " [Rendered Hook] "; }
~cleanup { echo " [Cleanup Hook] "; }