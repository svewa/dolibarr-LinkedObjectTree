/**
 * Linked Object Tree JavaScript
 * Handles tree view interactions
 */

var LinkedObjectTree = {
	/**
	 * Initialize tree view
	 */
	init: function() {
		// Attach event handlers for toggle buttons
		document.addEventListener('click', function(e) {
			// Check if clicked element is a toggle button or its child
			var toggleBtn = e.target.closest('.linkedobjecttree-toggle-btn');
			if (toggleBtn) {
				e.preventDefault();
				e.stopPropagation();
				LinkedObjectTree.toggleNode(toggleBtn);
			}
		});
	},

	/**
	 * Toggle a node's children visibility
	 */
	toggleNode: function(toggleBtn) {
		var row = toggleBtn.closest('tr');
		if (!row) return;

		var depth = parseInt(row.getAttribute('data-depth'));
		var isExpanded = row.classList.contains('expanded') || !row.classList.contains('collapsed');
		
		// Toggle the icon
		var icon = toggleBtn.querySelector('.fa');
		if (icon) {
			if (isExpanded) {
				// Collapse
				icon.classList.remove('fa-minus-square');
				icon.classList.add('fa-plus-square');
				row.classList.add('collapsed');
				row.classList.remove('expanded');
			} else {
				// Expand
				icon.classList.remove('fa-plus-square');
				icon.classList.add('fa-minus-square');
				row.classList.remove('collapsed');
				row.classList.add('expanded');
			}
		}

		// Toggle visibility of all child rows
		var nextRow = row.nextElementSibling;
		while (nextRow) {
			var nextDepth = parseInt(nextRow.getAttribute('data-depth'));
			
			// Stop when we reach a row that's not a descendant
			if (nextDepth <= depth) {
				break;
			}
			
			if (isExpanded) {
				// Collapsing - hide all descendants
				nextRow.style.display = 'none';
			} else {
				// Expanding - show direct children only
				if (nextDepth === depth + 1) {
					nextRow.style.display = '';
					
					// Mark this child as collapsed and update its icon
					nextRow.classList.add('collapsed');
					nextRow.classList.remove('expanded');
					
					// Update the child's toggle icon to show collapsed state
					var childToggle = nextRow.querySelector('.linkedobjecttree-toggle-btn .fa');
					if (childToggle) {
						childToggle.classList.remove('fa-minus-square');
						childToggle.classList.add('fa-plus-square');
					}
					
					// Hide all descendants of this child
					var skipDepth = nextDepth;
					var skipRow = nextRow.nextElementSibling;
					while (skipRow) {
						var skipRowDepth = parseInt(skipRow.getAttribute('data-depth'));
						if (skipRowDepth <= skipDepth) {
							break;
						}
						skipRow.style.display = 'none';
						skipRow = skipRow.nextElementSibling;
					}
				}
			}
			
			nextRow = nextRow.nextElementSibling;
		}
	}
};

// Initialize when DOM is ready
if (document.readyState === 'loading') {
	document.addEventListener('DOMContentLoaded', function() {
		LinkedObjectTree.init();
	});
} else {
	LinkedObjectTree.init();
}
