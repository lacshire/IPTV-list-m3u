var UI_channel_controls = null;
var UI_order_controls = null;

var UI_group_input = null;

var UI_group_name = null;
var UI_group_type_0 = null;	// Default
var UI_group_type_1 = null;	// Series
var UI_group_type_2 = null;	// Season
var UI_group_year = null;
var UI_group_season = null;

var UI_content_input = null;

var UI_content_name = null;
var UI_guide_name = null;
var UI_season = null;
var UI_episode = null;
var UI_year = null;
var UI_URL = null;
var UI_stream_type = null;
var UI_subtitle_URL = null;
var UI_poster_URL = null;
var UI_headers = null;

var channel_numbers = null;
var channel_numbers_root_node = null;

var groups_and_content = null;
var groups_and_content_root_node = null;

var ungrouped_content = null;
var ungrouped_content_root_node = null;

var focused_container = null;
var selected_nodes = null;
var focused_node = null;
var hovered_node = null;

var UI_order_children = null;

var g_allow_insert = false;
var g_order_channels = false;

var g_request_path = "/Erku_Web_Manager";	// For get_content.php requests.
var g_manager_request_path = g_request_path + "/manager";

class DB_TYPE {
    static #_LIVE_TV = 0;
    static #_VOD_MOVIES = 1;
	static #_VOD_TV_SHOWS = 2;

    static get LIVE_TV() { return this.#_LIVE_TV; }
    static get VOD_MOVIES() { return this.#_VOD_MOVIES; }
	static get VOD_TV_SHOWS() { return this.#_VOD_TV_SHOWS; }
}

class ITEM_TYPE {
    static #_GROUP = 0;
    static #_CONTENT = 1;

    static get GROUP() { return this.#_GROUP; }
    static get CONTENT() { return this.#_CONTENT; }
}

var g_database_type = -1;

function SetSelectNodes( node, end_node, deselect, depth = 0 )
{
	while ( node != end_node )
	{
		if ( node == null )
		{
			return false;
		}

		if ( deselect )
		{
			node.classList.remove( 'selected' );
		}
		else
		{
			node.classList.add( 'selected' );
		}

		if ( node.childElementCount == 2 && node.childNodes[ 1 ].hasChildNodes() )
		{
			var ret = SetSelectNodes( node.childNodes[ 1 ].firstElementChild, end_node, deselect, depth + 1 );
			if ( ret )
			{
				break;
			}
		}

		if ( depth == 0 )
		{
			while ( node != null && node.nextElementSibling == null && node.parentNode != focused_container )
			{
				node = node.parentNode;
			}
		}
		node = node.nextElementSibling;
	}

	return true;
}

function handleDragStart( e )
{
	if ( e.stopPropagation )
	{
		e.stopPropagation(); // Prevents parents from being dragged if we started dragging a child.
	}

	var last_focused_container = focused_container;
	if ( e.target.closest( ".ungrouped" ) != null )
	{
		focused_container = ungrouped_content_root_node;
	}
	else if ( e.target.closest( ".numbers" ) != null )
	{
		focused_container = channel_numbers_root_node;
	}
	else
	{
		focused_container = groups_and_content_root_node;
	}

	if ( last_focused_container != null && last_focused_container != focused_container )
	{
		var focused_nodes = last_focused_container.querySelectorAll( ".selected" );
		focused_nodes.forEach( function( node )
		{
			node.classList.remove( 'selected' );
		} );
	}

	var target_node = e.target.closest( ".box" );

	selected_nodes = focused_container.querySelectorAll( ".selected" );	

	if ( !target_node.classList.contains( "selected" ) )
	{
		selected_nodes.forEach( function( node )
		{
			node.classList.remove( "selected" );
		} );

		target_node.classList.add( "selected" );
	}

	if ( focused_node != null )
	{
		if ( !focused_node.classList.contains( "selected" ) )
		{
			focused_node.classList.remove( "focused" );
			focused_node = target_node;
			focused_node.classList.add( "focused" );
		}
	}
	else
	{
		focused_node = target_node;
		focused_node.classList.add( "focused" );
	}

	selected_nodes = focused_container.querySelectorAll( ".selected" );

	e.dataTransfer.setDragImage( new Image( 0, 0 ), 0, 0 );
	e.dataTransfer.effectAllowed = 'move';
}

function handleDragOver( e )
{
	if ( e.preventDefault )
	{
		e.preventDefault();
	}

	e.dataTransfer.dropEffect = 'move';
	
	if ( e.stopPropagation )
	{
		e.stopPropagation(); // Prevents parents from being dragged if we started dragging a child.
	}

	if ( hovered_node != null )
	{
		hovered_node.classList.remove( "over_top" );
		hovered_node.classList.remove( "over_bottom" );
		hovered_node.classList.remove( "over_all" );
	}

	hovered_node = e.target.closest( ".box" );

	if ( hovered_node == null )
	{
		return false;
	}

	if ( !g_allow_insert )
	{
		if ( hovered_node.dataset.type == ITEM_TYPE.CONTENT )
		{
			hovered_node = hovered_node.parentNode.parentNode;
		}

		hovered_node.classList.add( "over_all" );
	}
	else
	{
		var rc = hovered_node.getBoundingClientRect();
		var relative_mouse_y = e.clientY - rc.top;
		var height = rc.bottom - rc.top;

		// Hovered over group.
		/*if ( hovered_node.dataset.type == ITEM_TYPE.GROUP && ( relative_mouse_y < ( height - 10 ) && relative_mouse_y > 10 ) )
		{
			hovered_node.classList.add( 'over_all' );
		}
		else*/ if ( relative_mouse_y > ( height / 2 ) ) // Is the mouse over the top half of the element, or the bottom half?
		{
			hovered_node.classList.add( "over_bottom" );
			hovered_node.classList.remove( "over_top" );
			hovered_node.classList.remove( "over_all" );
		}
		else
		{
			hovered_node.classList.add( "over_top" );
			hovered_node.classList.remove( "over_bottom" );
			hovered_node.classList.remove( "over_all" );
		}
	}

	return false;
}

function handleDragLeave( e )
{
	var target_node = e.target.closest( ".box" );
	if ( target_node != null )
	{
		target_node.classList.remove( "over_top" );
		target_node.classList.remove( "over_bottom" );
		target_node.classList.remove( "over_all" );
	}
}

function handleDrop( e )
{
	if ( e.stopPropagation )
	{
		e.stopPropagation(); // Stops the browser from redirecting.
	}

	if ( selected_nodes != null && selected_nodes.length != 0 )
	{
		var target_node = e.target.closest( ".box" );

		if ( target_node == null )
		{
			return false;
		}

		if ( !g_allow_insert )
		{
			if ( target_node.dataset.type == ITEM_TYPE.CONTENT )
			{
				target_node = target_node.parentNode.parentNode;
			}
		}
		
		// If the first item is a group item, then we'll only move groups items.
		// Likewise, if the first item is a content item, then we'll only move content items.
		var item_type = selected_nodes[ 0 ].dataset.type;

		// If we attempt to drop a group into the Ungrouped Content container, then don't drop it.
		if ( target_node.dataset.id == -1 && item_type == 0 )
		{
			alert( "Cannot add group items into the Ungrouped Content group." );
			return false;
		}

		// If we're dropping an item into the group and the group is selected, then we'll bail.
		// We can't move a selected group into itself.
		//var target_is_selected = target_node.classList.contains( "selected" );

		// move_type: 1 = Move into group, 2 = Move next to sibling
		// target: the node we've hovered over.
		// sibling_position: 1 = Above, 2 = Below (for move_type = 2)
		// node_type: 1 = Groups, 2 = Content
		// nodes: Array of nodes that we're moving.
		// node_ids: Array of IDs of the nodes we're moving and their Parent IDs.
		var move_node_list = { "move_type": 0, "target": target_node, "sibling_position": 0, "node_type": 0, "nodes": [], "node_ids": [] };

		var alert_displayed1 = false;
		//var alert_displayed2 = false;

		var rc = target_node.getBoundingClientRect();
		var relative_mouse_y = e.clientY - rc.top;
		var height = rc.bottom - rc.top;

		for ( var i = 0; i < selected_nodes.length; ++i )
		{
			var drag_el = selected_nodes[ i ];

			// Only move nodes of the first selected type.
			if ( item_type == drag_el.dataset.type )
			{
				// Hovered over group.
				if ( !g_allow_insert &&
					 target_node.dataset.id != drag_el.dataset.id &&
					 /*target_node.dataset.id != drag_el.parentNode.parentNode.dataset.id &&*/
				   ( target_node.dataset.type == ITEM_TYPE.GROUP /*&& ( relative_mouse_y < ( height - 10 ) && relative_mouse_y > 10 )*/ ) )
				{
					if ( drag_el.dataset.type == ITEM_TYPE.GROUP )	// Group.
					{
						// We can only move groups into empty groups.
						if ( target_node.dataset.content_count == 0 )
						{
							if ( !drag_el.contains( target_node ) )
							{
								// If the first node we drag into the target is a group node, then only move groups.
								if ( move_node_list.node_type == 0 || move_node_list.node_type == 1 )
								{
									move_node_list.move_type = 1;	// Move into group.
									move_node_list.node_type = 1;	// Moving groups.
									move_node_list.nodes.push( drag_el );
									move_node_list.node_ids.push( { "parent_id": drag_el.parentNode.parentNode.dataset.id,
																	"id": drag_el.dataset.id } );
								}
							}
							/*else
							{
								if ( !alert_displayed2 )
								{
									alert_displayed2 = true;
									alert( "Cannot move parent group into child group." );
								}
							}*/
						}
						else
						{
							if ( !alert_displayed1 )
							{
								alert_displayed1 = true;
								alert( "The selected group cannot be added as a subgroup because the target group has content in it.\r\n\r\nMove the content to a different group before adding a subgroup." );
							}
						}
					}
					else if ( drag_el.dataset.type == ITEM_TYPE.CONTENT )	// Content.
					{
						// Make sure the group that we move our content node into is either empty, or has content in it.
						if ( target_node.dataset.content_count > 0 ||
							( target_node.dataset.content_count == 0 && !target_node.lastElementChild.hasChildNodes() ) )
						{
							// If the first node we drag into the target is a content node, then only move content nodes.
							if ( move_node_list.node_type == 0 || move_node_list.node_type == 2 )
							{
								move_node_list.move_type = 1;	// Move into group.
								move_node_list.node_type = 2;	// Moving content.
								move_node_list.nodes.push( drag_el );
								move_node_list.node_ids.push( { "parent_id": drag_el.parentNode.parentNode.dataset.id,
																"id": drag_el.dataset.id } );
							}
						}
						else
						{
							if ( !alert_displayed1 )
							{
								alert_displayed1 = true;
								alert( "The selected content can only be added to empty groups or groups that already have content." );
							}
						}
					}
				}
				else if ( g_allow_insert &&
						  target_node.dataset.id != drag_el.dataset.id &&
						( ( target_node.dataset.type == ITEM_TYPE.GROUP && drag_el.dataset.type == ITEM_TYPE.GROUP ) ||
						  ( target_node.dataset.type == ITEM_TYPE.CONTENT && drag_el.dataset.type == ITEM_TYPE.CONTENT ) ) )	// Move groups or content in between other groups or content respectively.
				{
					var sibling_position = 1;	// Mouse is over the top half of the target.

					if ( relative_mouse_y > ( height / 2 ) ) // Mouse is over the bottom half of the target.
					{
						sibling_position = 2
					}

					if ( drag_el.dataset.type == ITEM_TYPE.GROUP )	// Group.
					{
						// If we hovered over the root group, then adjust the target to its first or last element.
						if ( target_node.dataset.id == 0 )
						{
							target_node = ( sibling_position == 1 ? target_node.lastElementChild.firstElementChild : target_node.lastElementChild.lastElementChild );
							move_node_list.target = target_node;
						}

						// If the first node we drag over the target is a group node, then only move groups.
						if ( target_node.dataset.id > 0 &&
						   ( move_node_list.node_type == 0 || move_node_list.node_type == 1 ) )
						{
							move_node_list.move_type = 2;	// Move next to sibling.
							move_node_list.node_type = 1;	// Moving groups.
							move_node_list.sibling_position = sibling_position;
							move_node_list.nodes.push( drag_el );
							move_node_list.node_ids.push( { "parent_id": drag_el.parentNode.parentNode.dataset.id,
															"id": drag_el.dataset.id } );
						}
					}
					else if ( drag_el.dataset.type == ITEM_TYPE.CONTENT )	// Content.
					{
						// If the first node we drag over the target is a content node, then only move content nodes.
						if ( move_node_list.node_type == 0 || move_node_list.node_type == 2 )
						{
							move_node_list.move_type = 2;	// Move next to sibling.
							move_node_list.node_type = 2;	// Moving content.
							move_node_list.sibling_position = sibling_position;
							move_node_list.nodes.push( drag_el );
							move_node_list.node_ids.push( { "parent_id": drag_el.parentNode.parentNode.dataset.id,
															"id": drag_el.dataset.id } );
						}
					}
				}
			}
		}

		if ( move_node_list.move_type == 1 )	// Move nodes into group.
		{
			if ( move_node_list.node_type == 1 )	// Moving group nodes into group.
			{
				DB_MoveGroup( move_node_list.target.dataset.id, move_node_list.node_ids ).then( function( move_group_ret )
				{
					if ( move_group_ret == 1 )	// DB_MoveGroup was successful.
					{
						// The target group is already expanded. We can move the node list into it.
						if ( move_node_list.target.dataset.expanded == 1 )
						{
							// Update the parent_id for the moved groups.
							for ( var i = 0; i < move_node_list.nodes.length; ++i )
							{
								move_node_list.nodes[ i ].dataset.parent_id = move_node_list.target.dataset.id;
							}

							move_node_list.target.lastElementChild.append( ...move_node_list.nodes );
						}
						else	// The target group is not expanded. We'll remove the old node list and then open the target group.
						{
							// Remove the nodes because they'll be recreated in GetData().
							for ( var i = 0; i < move_node_list.nodes.length; ++i )
							{
								move_node_list.nodes[ i ].parentNode.removeChild( move_node_list.nodes[ i ] );
							}

							GetData( move_node_list.target ).then( function( get_data )
							{
								if ( get_data == 1 )	// GetData was successful.
								{
									// Set the previously focused and selected nodes for the new group.
									var focused_node_id = -1;
									if ( focused_node != null )
									{
										focused_node.classList.remove( "focused" );
										focused_node_id = focused_node.dataset.id;
										focused_node = null;
									}

									var query_string = "";
									for ( var i = 0; i < move_node_list.nodes.length; ++i )
									{
										if ( query_string != "" )
										{
											query_string += ","
										}
										query_string += "[data-id=\"" + move_node_list.nodes[ i ].dataset.id + "\"]";
									}

									selected_nodes = move_node_list.target.querySelectorAll( query_string );
									selected_nodes.forEach( function( node )
									{
										if ( focused_node_id != -1 && node.dataset.id == focused_node_id )
										{
											focused_node = node;
											focused_node.classList.add( "focused" );
										}
										node.classList.add( 'selected' );
									} );
								}
							} );
						}
					}
					else
					{
						alert( "Unable to move group into group." );
					}
				} );
			}
			else if ( move_node_list.node_type == 2 )	// Moving content nodes into group.
			{
				DB_MoveContent( move_node_list.target.dataset.id, move_node_list.node_ids ).then( function( move_content_ret )
				{
					if ( move_content_ret == 1 )	// DB_MoveContent was successful.
					{
						for ( var i = 0; i < move_node_list.nodes.length; ++i )
						{
							// Decrease the content count from the node's previous group.
							var parent_node = move_node_list.nodes[ i ].parentNode.parentNode;
							--parent_node.dataset.content_count;
							var content_count_string = ( parent_node.dataset.content_count > 0 ? " (" + parent_node.dataset.content_count + ")" : "" );
							if ( parent_node.dataset.name == "" )
							{
								if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && parent_node.dataset.group_type == 2 )
								{
									parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Season " + parent_node.dataset.group_season + content_count_string;
								}
								else
								{
									parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Empty" + content_count_string;
								}
							}
							else
							{
								parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = parent_node.dataset.name + content_count_string;
							}

							// Update the name depending on the group type we're moving the node into.
							if ( move_node_list.nodes[ i ].dataset.name == "" )
							{
								if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && move_node_list.target.dataset.group_type == 2 )
								{
									move_node_list.nodes[ i ].innerHTML = "Episode " + move_node_list.nodes[ i ].dataset.episode;
								}
								else
								{
									move_node_list.nodes[ i ].innerHTML = "Season " + move_node_list.nodes[ i ].dataset.season + " - Episode " + move_node_list.nodes[ i ].dataset.episode;
								}
							}

							// Remove the nodes because they'll be recreated in GetRequest().
							if ( move_node_list.target.dataset.expanded == 0 )
							{
								move_node_list.nodes[ i ].parentNode.removeChild( move_node_list.nodes[ i ] );
							}
						}

						var group_name = "";
						if ( move_node_list.target.dataset.name == "" )
						{
							if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && move_node_list.target.dataset.group_type == 2 )
							{
								group_name = "Season " + move_node_list.target.dataset.group_season;
							}
							else
							{
								group_name = "Empty";
							}
						}
						else
						{
							group_name = move_node_list.target.dataset.name;
						}

						// The target group is already expanded. We can move the node list into it.
						if ( move_node_list.target.dataset.expanded == 1 )
						{
							// Increase the content count of the target group.
							move_node_list.target.dataset.content_count = parseInt( move_node_list.target.dataset.content_count ) + move_node_list.nodes.length;
							move_node_list.target.childNodes[ 0 ].childNodes[ 1 ].innerHTML = group_name + " (" + move_node_list.target.dataset.content_count + ")";

							move_node_list.target.lastElementChild.append( ...move_node_list.nodes );
						}
						else	// The target group is not expanded. We'll remove the old node list (above) and then open the target group.
						{
							GetRequest( move_node_list.target ).then( function( get_request_ret )
							{
								if ( get_request_ret == 1 )
								{
									move_node_list.target.dataset.expanded = 1;
									move_node_list.target.childNodes[ 0 ].childNodes[ 0 ].innerHTML = "[ - ]";

									// Increase the content count of the target group.
									move_node_list.target.dataset.content_count = parseInt( move_node_list.target.dataset.content_count ) + move_node_list.nodes.length;
									move_node_list.target.childNodes[ 0 ].childNodes[ 1 ].innerHTML = group_name + " (" + move_node_list.target.dataset.content_count + ")";
								
									// Set the previously focused and selected nodes for the new group.
									var focused_node_id = -1;
									if ( focused_node != null )
									{
										focused_node.classList.remove( "focused" );
										focused_node_id = focused_node.dataset.id;
										focused_node = null;
									}

									var query_string = "";
									for ( var i = 0; i < move_node_list.nodes.length; ++i )
									{
										if ( query_string != "" )
										{
											query_string += ","
										}
										query_string += "[data-id=\"" + move_node_list.nodes[ i ].dataset.id + "\"]";
									}

									selected_nodes = move_node_list.target.querySelectorAll( query_string );
									selected_nodes.forEach( function( node )
									{
										if ( focused_node_id != -1 && node.dataset.id == focused_node_id )
										{
											focused_node = node;
											focused_node.classList.add( "focused" );
										}
										node.classList.add( 'selected' );
									} );
								}
							} );
						}
					}
					else
					{
						alert( "Unable to move content into group." );
					}
				} );
			}
		}
		else if ( move_node_list.move_type == 2 )	// Move nodes next to siblings.
		{
			if ( move_node_list.node_type == 1 )	// Moving group nodes next to other group nodes.
			{
				DB_MoveGroup( move_node_list.target.dataset.parent_id, move_node_list.node_ids ).then( function( move_group_ret )
				{
					if ( move_group_ret == 1 )	// DB_MoveGroup was successful.
					{
						// Update the parent_id for the moved groups.
						for ( var i = 0; i < move_node_list.nodes.length; ++i )
						{
							move_node_list.nodes[ i ].dataset.parent_id = move_node_list.target.dataset.parent_id;
						}

						if ( move_node_list.sibling_position == 1 )	// Move nodes above target node.
						{
							move_node_list.target.before( ...move_node_list.nodes );
						}
						else if ( move_node_list.sibling_position == 2 )	// Move nodes below target node.
						{
							move_node_list.target.after( ...move_node_list.nodes );
						}

						var ids = [];

						for ( const node of move_node_list.target.parentNode.childNodes )
						{
							ids.push( node.dataset.id );
						}

						DB_SaveGroupOrder( ids ).then( function( save_group_order_ret )
						{
							if ( save_group_order_ret != 1 )	// DB_SaveGroupOrder was successful.
							{
								alert( "Group order was not saved." );
							}
						} );
					}
					else
					{
						alert( "Unable to move group next to group." );
					}
				} );
			}
			else if ( move_node_list.node_type == 2 )	// Moving content nodes next to other content nodes.
			{
				if ( !g_order_channels )
				{
					var target_parent_node = move_node_list.target.parentNode.parentNode;

					DB_MoveContent( target_parent_node.dataset.id, move_node_list.node_ids ).then( function( move_content_ret )
					{
						if ( move_content_ret == 1 )	// DB_MoveContent was successful.
						{
							for ( var i = 0; i < move_node_list.nodes.length; ++i )
							{
								// Decrease the content count from the node's previous group.
								var parent_node = move_node_list.nodes[ i ].parentNode.parentNode;
								--parent_node.dataset.content_count;
								var content_count_string = ( parent_node.dataset.content_count > 0 ? " (" + parent_node.dataset.content_count + ")" : "" );
								if ( parent_node.dataset.name == "" )
								{
									if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && parent_node.dataset.group_type == 2 )
									{
										parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Season " + parent_node.dataset.group_season + content_count_string;
									}
									else
									{
										parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Empty" + content_count_string;
									}
								}
								else
								{
									parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = parent_node.dataset.name + content_count_string;
								}

								// Update the name depending on the group type we're moving the node into.
								if ( move_node_list.nodes[ i ].dataset.name == "" )
								{
									if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && target_parent_node.dataset.group_type == 2 )
									{
										move_node_list.nodes[ i ].innerHTML = "Episode " + move_node_list.nodes[ i ].dataset.episode;
									}
									else
									{
										move_node_list.nodes[ i ].innerHTML = "Season " + move_node_list.nodes[ i ].dataset.season + " - Episode " + move_node_list.nodes[ i ].dataset.episode;
									}
								}
							}

							var group_name = "";
							if ( target_parent_node.dataset.name == "" )
							{
								if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && target_parent_node.dataset.group_type == 2 )
								{
									group_name = "Season " + target_parent_node.dataset.group_season;
								}
								else
								{
									group_name = "Empty";
								}
							}
							else
							{
								group_name = target_parent_node.dataset.name;
							}

							// Increase the content count of the target group.
							target_parent_node.dataset.content_count = parseInt( target_parent_node.dataset.content_count ) + move_node_list.nodes.length;
							target_parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = group_name + " (" + target_parent_node.dataset.content_count + ")";

							if ( move_node_list.sibling_position == 1 )	// Move nodes above target node.
							{
								move_node_list.target.before( ...move_node_list.nodes );
							}
							else if ( move_node_list.sibling_position == 2 )	// Move nodes below target node.
							{
								move_node_list.target.after( ...move_node_list.nodes );
							}

							var ids = [];

							for ( const node of move_node_list.target.parentNode.childNodes )
							{
								ids.push( node.dataset.id );
							}

							DB_SaveContentOrder( ids ).then( function( save_content_order_ret )
							{
								if ( save_content_order_ret != 1 )	// DB_SaveContentOrder was successful.
								{
									alert( "Content order was not saved." );
								}
							} );
						}
						else
						{
							alert( "Unable to move content next to content." );
						}
					} );
				}
				else	// Reorder Live TV channel numbers.
				{
					if ( move_node_list.sibling_position == 1 )	// Move nodes above target node.
					{
						move_node_list.target.before( ...move_node_list.nodes );
					}
					else if ( move_node_list.sibling_position == 2 )	// Move nodes below target node.
					{
						move_node_list.target.after( ...move_node_list.nodes );
					}
				}
			}
		}
	}

	return false;
}

function handleDragEnd( e )
{
	let items = container.querySelectorAll( ".box" );
	items.forEach( function( item )
	{
		item.classList.remove( 'over_top' );
		item.classList.remove( 'over_bottom' );
		item.classList.remove( 'over_all' );
	} );
}

function handleSelection( e )
{
	if ( e.stopPropagation )
	{
		e.stopPropagation(); // Prevents parents from being selected if we clicked a child.
	}

	// Expand or collapse groups.
	if ( e.target.matches( ".group_expand" ) )
	{
		var target_node = e.target.parentNode.parentNode;

		if ( target_node.dataset.type == ITEM_TYPE.GROUP && target_node.dataset.id > 0 )
		{
			if ( target_node.dataset.expanded == 0 )
			{
				if ( target_node.dataset.content_count > 0 )
				{
					GetRequest( target_node ).then( function( get_request_ret )
					{
						if ( get_request_ret == 1 )
						{
							target_node.dataset.expanded = 1;
							e.target.innerHTML = "[ - ]";
						}
					} );
				}
				else
				{
					target_node.dataset.expanded = 1;
					e.target.innerHTML = "[ - ]";

					GetData( target_node );
				}
			}
			else
			{
				target_node.dataset.expanded = 0;
				e.target.innerHTML = "[ + ]";

				target_node.lastElementChild.innerHTML = "";
			}
		}
	}
	else	// Select the node.
	{
		var last_focused_container = focused_container;
		if ( e.target.closest( ".ungrouped" ) != null )
		{
			channel_numbers_root_node.classList.remove( 'selected_container' );
			groups_and_content_root_node.classList.remove( 'selected_container' );
			focused_container = ungrouped_content_root_node;
		}
		else if ( e.target.closest( ".numbers" ) != null )
		{
			ungrouped_content_root_node.classList.remove( 'selected_container' );
			groups_and_content_root_node.classList.remove( 'selected_container' );
			focused_container = channel_numbers_root_node;
		}
		else
		{
			channel_numbers_root_node.classList.remove( 'selected_container' );
			ungrouped_content_root_node.classList.remove( 'selected_container' );
			focused_container = groups_and_content_root_node;
		}

		if ( last_focused_container != null && last_focused_container != focused_container )
		{
			var focused_nodes = last_focused_container.querySelectorAll( ".selected" );
			focused_nodes.forEach( function( node )
			{
				node.classList.remove( 'selected' );
			} );
		}

		focused_container.classList.add( 'selected_container' );

		var target_node = e.target.closest( ".box" );

		if ( target_node != null )
		{
			selected_nodes = focused_container.querySelectorAll( ".selected" );

			// Exclude the root nodes (Groups and Content, and Ungrouped Content).
			if ( ( target_node.dataset.type == ITEM_TYPE.GROUP && target_node.dataset.id > 0 ) ||
				 ( target_node.dataset.type == ITEM_TYPE.CONTENT && target_node.dataset.id >= 0 ) )
			{
				if ( e.shiftKey && focused_node != null && last_focused_container == focused_container )
				{
					if ( target_node.compareDocumentPosition( focused_node ) & Node.DOCUMENT_POSITION_FOLLOWING )
					{
						if ( e.ctrlKey )
						{
							focused_node.classList.remove( 'selected' );
						}
						else
						{
							focused_node.classList.add( 'selected' );
						}

						SetSelectNodes( target_node, focused_node, e.ctrlKey );
					}
					else if ( target_node.compareDocumentPosition( focused_node ) & Node.DOCUMENT_POSITION_PRECEDING )
					{
						SetSelectNodes( focused_node, target_node, e.ctrlKey );

						if ( e.ctrlKey )
						{
							target_node.classList.remove( 'selected' );
						}
						else
						{
							target_node.classList.add( 'selected' );
						}
					}
				}
				else
				{
					var is_selected = target_node.classList.contains( "selected" );

					if ( !e.ctrlKey )
					{
						if ( selected_nodes.length > 1 && is_selected )
						{
							is_selected = false;
						}

						selected_nodes.forEach( function( node )
						{
							node.classList.remove( 'selected' );
						} );
					}

					if ( is_selected )
					{
						target_node.classList.remove( 'selected' );
					}
					else
					{
						target_node.classList.add( 'selected' );
					}
				}

				if ( focused_node != null )
				{
					focused_node.classList.remove( "focused" );
				}
				focused_node = target_node;
				focused_node.classList.add( "focused" );

				var focused_node_is_selected = focused_node.classList.contains( "selected" );

				if ( focused_node.dataset.type == ITEM_TYPE.GROUP )
				{
					UI_group_name.value = ( focused_node_is_selected ? focused_node.dataset.name : "" );
					UI_group_year.value = ( focused_node_is_selected ? focused_node.dataset.group_year : "" );
					UI_group_season.value = ( focused_node_is_selected ? focused_node.dataset.group_season : "" );
					SetGroupType( ( focused_node_is_selected ? focused_node.dataset.group_type : 0 ) );
				}
				else if ( focused_node.dataset.type == ITEM_TYPE.CONTENT )
				{
					UI_content_name.value = ( focused_node_is_selected ? focused_node.dataset.name : "" );
					UI_guide_name.value = ( focused_node_is_selected ? focused_node.dataset.guide_name : "" );
					UI_season.value = ( focused_node_is_selected ? focused_node.dataset.season : "" );
					UI_episode.value = ( focused_node_is_selected ? focused_node.dataset.episode : "" );
					UI_year.value = ( focused_node_is_selected ? focused_node.dataset.year : "" );
					UI_URL.value = ( focused_node_is_selected ? focused_node.dataset.url : "" );
					UI_stream_type.value = ( focused_node_is_selected ? focused_node.dataset.extension : "" );
					UI_subtitle_URL.value = ( focused_node_is_selected ? focused_node.dataset.subtitle_url : "" );
					UI_poster_URL.value = ( focused_node_is_selected ? focused_node.dataset.logo_url : "" );
					UI_headers.value = ( focused_node_is_selected ? focused_node.dataset.headers : "" );
				}

				if ( !focused_node_is_selected )
				{
					if ( focused_node != null )
					{
						focused_node.classList.remove( "focused" );
					}
					focused_node = null;
				}

				selected_nodes = focused_container.querySelectorAll( ".selected" );
			}
			else	// Root node was selected. Deselect all of its children.
			{
				if ( focused_node != null )
				{
					focused_node.classList.remove( "focused" );
				}
				focused_node = null;

				selected_nodes.forEach( function( node )
				{
					node.classList.remove( 'selected' );
				} );
			}
		}
	}
}

function GetUngroupedContent()
{
	DB_GetUngroupedContent().then( function( get_ungrouped_content_ret )
	{
		if ( get_ungrouped_content_ret != null )	// DB_GetUngroupedContent was successful.
		{
			var tmp_node = document.createElement( "div" );

			var box = ungrouped_content_root_node.lastElementChild;

			var json = JSON.parse( get_ungrouped_content_ret );

			var type = json.data.type;
			var values = json.data.values;
			values.forEach( ( item ) =>
			{
				if ( type == ITEM_TYPE.CONTENT )
				{
					var div = document.createElement( "div" );
					div.classList.add( "box" );
					div.draggable = true;

					div.dataset.type = type;
					div.dataset.id = item.id;
					div.dataset.name = item.name;

					div.dataset.guide_name = ( item.guide_name != undefined ? item.guide_name : "" );
					div.dataset.season = ( item.season != undefined ? item.season : "" );
					div.dataset.episode = ( item.episode != undefined ? item.episode : "" );
					div.dataset.year = ( item.year != undefined ? item.year : "" );
					div.dataset.extension = ( item.extension != undefined ? item.extension : "" );
					div.dataset.subtitle_url = ( item.subtitle_url != undefined ? item.subtitle_url : "" );
					div.dataset.url = ( item.url != undefined ? item.url : "" );
					div.dataset.logo_url = ( item.logo_url != undefined ? item.logo_url : "" );
					div.dataset.headers = ( item.headers != undefined ? item.headers : "" );

					if ( div.dataset.name == "" )
					{
						if ( g_database_type == DB_TYPE.VOD_TV_SHOWS )
						{
							div.innerHTML = "Season " + div.dataset.season + " - Episode " + div.dataset.episode;
						}
						else
						{
							div.innerHTML = "Empty";
						}
					}
					else
					{
						div.innerHTML = div.dataset.name;
					}

					tmp_node.appendChild( div );
				}
			} );

			ungrouped_content_root_node.dataset.content_count = values.length;
			ungrouped_content_root_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = ungrouped_content_root_node.dataset.name + " (" + ungrouped_content_root_node.dataset.content_count + ")";

			box.append( ...tmp_node.childNodes );
		}
	} );
}

function SetUI( data_type )
{
	// These are shown for all database types.
	// UI_group_input.children[ 1 ].style.display = "block";		// Group Name
	//
	//UI_content_input.children[ 1 ].style.display = "block";		// Name
	//UI_content_input.children[ 6 ].style.display = "block";		// URL
	//UI_content_input.children[ 7 ].style.display = "block";		// Stream Type
	//UI_content_input.children[ 9 ].style.display = "block";		// Poster URL
	//UI_content_input.children[ 10 ].style.display = "block";		// Headers

	switch ( g_database_type )
	{
		case DB_TYPE.LIVE_TV:
		{
			UI_group_input.children[ 2 ].style.display = "none";		// Group Type
			UI_group_input.children[ 3 ].style.display = "none";		// Group Year
			UI_group_input.children[ 4 ].style.display = "none";		// Group Season

			UI_content_input.children[ 2 ].style.display = "block";		// Guide Name
			UI_content_input.children[ 3 ].style.display = "none";		// Season
			UI_content_input.children[ 4 ].style.display = "none";		// Episode
			UI_content_input.children[ 5 ].style.display = "none";		// Year
			UI_content_input.children[ 8 ].style.display = "none";		// Subtitle URL
		}
		break;

		case DB_TYPE.VOD_MOVIES:
		{
			UI_group_input.children[ 2 ].style.display = "none";		// Group Type
			UI_group_input.children[ 3 ].style.display = "none";		// Group Year
			UI_group_input.children[ 4 ].style.display = "none";		// Group Season

			UI_content_input.children[ 2 ].style.display = "none";		// Guide Name
			UI_content_input.children[ 3 ].style.display = "none";		// Season
			UI_content_input.children[ 4 ].style.display = "none";		// Episode
			UI_content_input.children[ 5 ].style.display = "block";		// Year
			UI_content_input.children[ 8 ].style.display = "block";		// Subtitle URL
		}
		break;

		case DB_TYPE.VOD_TV_SHOWS:
		{
			var group_type = GetGroupType();

			UI_group_input.children[ 2 ].style.display = "block";		// Group Type
			UI_group_input.children[ 3 ].style.display = ( group_type == 0 ? "none" : "block" );	// Group Year
			UI_group_input.children[ 4 ].style.display = ( group_type == 2 ? "block" : "none" );	// Group Season

			UI_content_input.children[ 2 ].style.display = "none";		// Guide Name
			UI_content_input.children[ 3 ].style.display = "block";		// Season
			UI_content_input.children[ 4 ].style.display = "block";		// Episode
			UI_content_input.children[ 5 ].style.display = "block";		// Year
			UI_content_input.children[ 8 ].style.display = "block";		// Subtitle URL
		}
		break;
	}
}

function LoadData( e )
{
	var data_type = parseInt( e.target.value )

	channel_numbers_root_node.lastElementChild.firstElementChild.innerHTML = "";
	channel_numbers_root_node.lastElementChild.lastElementChild.innerHTML = "";
	groups_and_content_root_node.lastElementChild.innerHTML = "";
	ungrouped_content_root_node.lastElementChild.innerHTML = "";

	SetGroupType( 0 );

	UI_order_children.checked = false;

	if ( data_type >= 0 && data_type <= 2 )
	{
		g_database_type = data_type;

		g_allow_insert = false;
		g_order_channels = false;

		UI_channel_controls.style.display = "none";
		UI_order_controls.style.display = "block";
		UI_group_input.style.display = "block";
		UI_content_input.style.display = "block";

		channel_numbers.style.display = "none";
		groups_and_content.style.display = "block";
		ungrouped_content.style.display = "block";

		SetUI( g_database_type );

		GetData();
	}
	else if ( data_type == 3 )
	{
		g_database_type = DB_TYPE.LIVE_TV;

		g_allow_insert = true;
		g_order_channels = true;

		UI_channel_controls.style.display = "block";
		UI_order_controls.style.display = "none";
		UI_group_input.style.display = "none";
		UI_content_input.style.display = "block";

		channel_numbers.style.display = "block";
		groups_and_content.style.display = "none";
		ungrouped_content.style.display = "none";

		SetUI( g_database_type );

		DB_GetAllChannels();
	}
	else
	{
		g_database_type = -1;

		g_allow_insert = false;
		g_order_channels = false;

		UI_channel_controls.style.display = "none";
		UI_order_controls.style.display = "none";
		UI_group_input.style.display = "none";
		UI_content_input.style.display = "none";

		channel_numbers.style.display = "none";
		groups_and_content.style.display = "none";
		ungrouped_content.style.display = "none";
	}
}

function GetData( group_node = null )
{
	var group_id = ( group_node != null ? group_node.dataset.id : 0 );

	var p = new Promise( function( resolve, reject )
	{
		DB_GetAllGroups( group_id ).then( function( get_all_groups_ret )
		{
			if ( get_all_groups_ret != null )	// DB_GetAllGroups was successful.
			{
				var tmp_node = document.createElement( "div" );

				var json = JSON.parse( get_all_groups_ret );

				if ( group_node != null )
				{
					group_node.dataset.expanded = 1;
					group_node.childNodes[ 0 ].childNodes[ 0 ].innerHTML = "[ - ]";
				}

				var box = ( group_node == null ? groups_and_content_root_node.lastElementChild : group_node.lastElementChild );

				var type = json.data.type;
				var values = json.data.values;
				values.forEach( ( item ) =>
				{
					// Don't include the default groups.
					// All added groups will start with an ID of 10000.
					if ( type == ITEM_TYPE.GROUP && item.id >= 10000 )
					{
						var div = document.createElement( "div" );
						div.id = "g" + item.id;
						div.classList.add( "group" );
						div.classList.add( "box" );
						div.draggable = true;

						div.dataset.type = type;
						div.dataset.id = item.id;
						div.dataset.parent_id = item.parent_id;
						div.dataset.name = item.name;
						div.dataset.group_type = ( item.type != undefined ? item.type : 0 );
						div.dataset.group_year = ( item.year != undefined ? item.year : 0 );
						div.dataset.group_season = ( item.season != undefined ? item.season : 0 );
						div.dataset.content_count = item.content_count

							var group_info = document.createElement( "div" );
							group_info.classList.add( "group_info" );

								var group_expand = document.createElement( "div" );
								group_expand.classList.add( "group_expand" );

								div.dataset.expanded = 0;
								group_expand.innerHTML = "[ + ]";

								var group_name = document.createElement( "div" );
								group_name.classList.add( "group_name" );
								var content_count_string = ( item.content_count > 0 ? " (" + item.content_count + ")" : "" );
								if ( div.dataset.name == "" )
								{
									if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && div.dataset.group_type == 2 )
									{
										group_name.innerHTML = "Season " + div.dataset.group_season + content_count_string;
									}
									else
									{
										group_name.innerHTML = "Empty" + content_count_string;
									}
								}
								else
								{
									group_name.innerHTML = div.dataset.name + content_count_string;
								}

								group_info.appendChild( group_expand );
								group_info.appendChild( group_name );

							var sub_group = document.createElement( "div" );

							div.appendChild( group_info );
							div.appendChild( sub_group );

						var parent = tmp_node.querySelector( "#g" + item.parent_id );
						if ( parent != null )
						{
							parent.lastElementChild.appendChild( div );
						}
						else
						{
							tmp_node.append( div );
						}
					}
				} );

				box.append( ...tmp_node.childNodes );
			}
		} ).then( () =>
		{
			if ( group_node == null )
			{
				GetUngroupedContent();
			}

			resolve( 1 );
		} );
	} );

	return p;
}

function AddGroupToDatabase()
{
	var target_node = null;
	
	if ( focused_node != null )
	{
		// Only add groups to other groups that don't have any content in them.
		if ( focused_node.dataset.type == ITEM_TYPE.GROUP && focused_node.dataset.content_count == 0 && focused_node.classList.contains( "selected" ) )
		{
			target_node = focused_node;
		}
	}
	else
	{
		target_node = groups_and_content_root_node;
	}

	// Only add groups to groups.
	if ( target_node != null )
	{
		var data = { "name": UI_group_name.value,
					 "group_type": GetGroupType(),
					 "group_year": UI_group_year.value,
					 "group_season": UI_group_season.value };

		DB_AddGroup( target_node.dataset.id, data ).then( function( add_group_ret )
		{
			if ( add_group_ret != -1 )	// DB_AddGroup was successful.
			{
				if ( target_node.dataset.expanded == 0 )
				{
					GetData( target_node ).then( function( get_data_ret )
					{
						if ( get_data_ret == 1 )
						{
							target_node.dataset.expanded = 1;
							target_node.childNodes[ 0 ].childNodes[ 0 ].innerHTML = "[ - ]";

							if ( selected_nodes != null )
							{
								selected_nodes.forEach( function( node )
								{
									node.classList.remove( 'selected' );
								} );
							}

							var old_target_node = target_node;
							target_node = old_target_node.lastElementChild.lastElementChild;
							target_node.classList.add( 'selected' );
							selected_nodes = old_target_node.querySelectorAll( ".selected" );

							// Deselect the group, and select the newly added content.
							if ( focused_node != null )
							{
								focused_node.classList.remove( "focused" );
							}
							focused_node = target_node;
							focused_node.classList.add( "focused" );
						}
					} );
				}
				else
				{
					var div = document.createElement( "div" );
					div.id = "g" + add_group_ret;
					div.classList.add( "group" );
					div.classList.add( "box" );
					div.draggable = true;

					div.dataset.type = ITEM_TYPE.GROUP;
					div.dataset.id = add_group_ret;
					div.dataset.parent_id = target_node.dataset.id;
					div.dataset.name = data.name;
					div.dataset.group_type = data.group_type;
					div.dataset.group_year = data.group_year;
					div.dataset.group_season = data.group_season;
					div.dataset.content_count = 0;

						var group_info = document.createElement( "div" );
						group_info.classList.add( "group_info" );

							var group_expand = document.createElement( "div" );
							group_expand.classList.add( "group_expand" );

							div.dataset.expanded = 1;
							group_expand.innerHTML = "[ - ]";

							var group_name = document.createElement( "div" );
							group_name.classList.add( "group_name" );
							if ( div.dataset.name == "" )
							{
								if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && div.dataset.group_type == 2 )
								{
									group_name.innerHTML = "Season " + div.dataset.group_season;
								}
								else
								{
									group_name.innerHTML = "Empty";
								}
							}
							else
							{
								group_name.innerHTML = div.dataset.name;
							}

							group_info.appendChild( group_expand );
							group_info.appendChild( group_name );

						var sub_group = document.createElement( "div" );

						div.appendChild( group_info );
						div.appendChild( sub_group );

					target_node.lastElementChild.appendChild( div );

					if ( selected_nodes != null )
					{
						selected_nodes.forEach( function( node )
						{
							node.classList.remove( 'selected' );
						} );
					}

					div.classList.add( 'selected' );
					selected_nodes = target_node.querySelectorAll( ".selected" );

					// Deselect the group, and select the newly added content.
					if ( focused_node != null )
					{
						focused_node.classList.remove( "focused" );
					}
					focused_node = div;
					focused_node.classList.add( "focused" );
				}
			}
			else
			{
				alert( "The group could not be added." );
			}
		} );
	}
	else
	{
		alert( "Groups can only be added to the root group, or a group without content." );
	}
}

function AddContentToDatabase()
{
	// Only add content to groups.
	if ( focused_node != null && focused_node.dataset.type == ITEM_TYPE.GROUP && focused_node.classList.contains( "selected" ) )
	{
		var target_node = focused_node;

		// Make sure the group that we add our content node into is either empty, or has content in it.
		if ( target_node.dataset.content_count > 0 ||
		   ( target_node.dataset.content_count == 0 && !target_node.lastElementChild.hasChildNodes() ) )
		{
			if ( UI_URL.value != "" && UI_stream_type.value == "" )
			{
				var url = UI_URL.value;
				var start = url.indexOf( '.', url.lastIndexOf( '/' ) + 1 );
				if ( start != -1 )
				{
					var ext = url.substr( start + 1 );
					var end = ext.search( /$|[?#]/ );

					ext = ext.substring( 0, end );
					start = ext.lastIndexOf( '.' );
					if ( start != -1 )
					{
						ext = ext.substring( start + 1 );
					}

					UI_stream_type.value = ext;
				}
			}

			var data = { "name": UI_content_name.value,
						 "guide_name": UI_guide_name.value,
						 "season": UI_season.value,
						 "episode": UI_episode.value,
						 "year": UI_year.value,
						 "url": UI_URL.value,
						 "extension": UI_stream_type.value,
						 "subtitle_url": UI_subtitle_URL.value,
						 "logo_url": UI_poster_URL.value,
						 "headers": UI_headers.value };

			DB_AddContent( target_node.dataset.id, data ).then( function( add_content_ret )
			{
				if ( add_content_ret != -1 )	// DB_AddContent was successful.
				{
					// Increase the content count of the target group.
					++target_node.dataset.content_count;

					var group_name = "";
					if ( target_node.dataset.name == "" )
					{
						if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && target_node.dataset.group_type == 2 )
						{
							group_name = "Season " + target_node.dataset.group_season;
						}
						else
						{
							group_name = "Empty";
						}
					}
					else
					{
						group_name = target_node.dataset.name;
					}

					target_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = group_name + " (" + target_node.dataset.content_count + ")";

					if ( target_node.dataset.expanded == 0 )
					{
						GetRequest( target_node ).then( function( get_request_ret )
						{
							if ( get_request_ret == 1 )
							{
								target_node.dataset.expanded = 1;
								target_node.childNodes[ 0 ].childNodes[ 0 ].innerHTML = "[ - ]";

								if ( selected_nodes != null )
								{
									selected_nodes.forEach( function( node )
									{
										node.classList.remove( 'selected' );
									} );
								}

								var old_target_node = target_node;
								target_node = old_target_node.lastElementChild.lastElementChild;
								for ( var i = 0; i < old_target_node.lastElementChild.childNodes.length; ++i )
								{
									if ( old_target_node.lastElementChild.childNodes[ i ].dataset.id == add_content_ret )
									{
										target_node = old_target_node.lastElementChild.childNodes[ i ];
										break;
									}
								}
								target_node.classList.add( 'selected' );
								selected_nodes = old_target_node.querySelectorAll( ".selected" );

								// Deselect the group, and select the newly added content.
								focused_node.classList.remove( "focused" );
								focused_node = target_node;
								focused_node.classList.add( "focused" );
							}
						} );
					}
					else
					{
						var div = document.createElement( "div" );
						div.classList.add( "box" );
						div.draggable = true;

						div.dataset.type = ITEM_TYPE.CONTENT;
						div.dataset.id = add_content_ret;
						div.dataset.name = data.name;

						div.dataset.guide_name = data.guide_name;

						div.dataset.season = data.season;
						div.dataset.episode = data.episode;
						div.dataset.year = data.year;

						div.dataset.url = data.url;
						div.dataset.extension = data.extension;
						div.dataset.subtitle_url = data.subtitle_url;
						div.dataset.logo_url = data.logo_url;
						div.dataset.headers = data.headers;

						if ( div.dataset.name == "" )
						{
							if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && target_node.dataset.group_type == 2 )
							{
								div.innerHTML = "Episode " + div.dataset.episode;
							}
							else
							{
								div.innerHTML = "Season " + div.dataset.season + " - Episode " + div.dataset.episode;
							}
						}
						else
						{
							div.innerHTML = div.dataset.name;
						}

						target_node.lastElementChild.appendChild( div );

						if ( selected_nodes != null )
						{
							selected_nodes.forEach( function( node )
							{
								node.classList.remove( 'selected' );
							} );
						}

						div.classList.add( 'selected' );
						selected_nodes = target_node.querySelectorAll( ".selected" );

						// Deselect the group, and select the newly added content.
						focused_node.classList.remove( "focused" );
						focused_node = div;
						focused_node.classList.add( "focused" );
					}
				}
				else
				{
					alert( "The content could not be added." );
				}
			} );
		}
		else
		{
			alert( "Content can only be added to empty groups or groups that already have content." );
		}
	}
	else
	{
		alert( "A group must be selected to add content." );
	}
}

function UpdateGroup()
{
	// Exclude the root group.
	if ( focused_node != null && focused_node.dataset.type == ITEM_TYPE.GROUP && focused_node.dataset.id > 0 )
	{
		var target_node = focused_node;

		var data = { "name": UI_group_name.value,
					 "group_type": GetGroupType(),
					 "group_year": UI_group_year.value,
					 "group_season": UI_group_season.value };

		DB_UpdateGroup( target_node.dataset.id, data ).then( function( update_group_ret )
		{
			if ( update_group_ret == 1 )	// DB_UpdateGroup was successful.
			{
				target_node.dataset.name = data.name;
				target_node.dataset.group_type = data.group_type;
				target_node.dataset.group_year = data.group_year;
				target_node.dataset.group_season = data.group_season;

				var content_count_string = ( target_node.dataset.content_count > 0 ? " (" + target_node.dataset.content_count + ")" : "" );
				if ( target_node.dataset.name == "" )
				{
					if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && target_node.dataset.group_type == 2 )
					{
						target_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Season " + target_node.dataset.group_season + content_count_string;
					}
					else
					{
						target_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Empty" + content_count_string;
					}
				}
				else
				{
					target_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = target_node.dataset.name + content_count_string;
				}
			}
		} );
	}
	else
	{
		alert( "You must select a group item to update." );
	}
}

function UpdateContent()
{
	if ( focused_node != null && focused_node.dataset.type == ITEM_TYPE.CONTENT )
	{
		var target_node = focused_node;

		if ( UI_URL.value != "" && UI_stream_type.value == "" )
		{
			var url = UI_URL.value;
			var start = url.indexOf( '.', url.lastIndexOf( '/' ) + 1 );
			if ( start != -1 )
			{
				var ext = url.substr( start + 1 );
				var end = ext.search( /$|[?#]/ );

				ext = ext.substring( 0, end );
				start = ext.lastIndexOf( '.' );
				if ( start != -1 )
				{
					ext = ext.substring( start + 1 );
				}

				UI_stream_type.value = ext;
			}
		}

		var data = { "name": UI_content_name.value,
					 "guide_name": UI_guide_name.value,
					 "season": UI_season.value,
					 "episode": UI_episode.value,
					 "year": UI_year.value,
					 "url": UI_URL.value,
					 "extension": UI_stream_type.value,
					 "subtitle_url": UI_subtitle_URL.value,
					 "logo_url": UI_poster_URL.value,
					 "headers": UI_headers.value };

		DB_UpdateContent( target_node.dataset.id, data ).then( function( update_content_ret )
		{
			if ( update_content_ret == 1 )	// DB_UpdateContent was successful.
			{
				target_node.dataset.name = data.name;

				target_node.dataset.guide_name = data.guide_name;

				target_node.dataset.season = data.season;
				target_node.dataset.episode = data.episode;
				target_node.dataset.year = data.year;

				target_node.dataset.url = data.url;
				target_node.dataset.extension = data.extension;
				target_node.dataset.subtitle_url = data.subtitle_url;
				target_node.dataset.logo_url = data.logo_url;
				target_node.dataset.headers = data.headers;

				if ( target_node.dataset.name == "" )
				{
					if ( g_database_type == DB_TYPE.VOD_TV_SHOWS )
					{
						if ( target_node.parentNode.parentNode.dataset.group_type == 2 )
						{
							target_node.innerHTML = "Episode " + target_node.dataset.episode;
						}
						else
						{
							target_node.innerHTML = "Season " + target_node.dataset.season + " - Episode " + target_node.dataset.episode;
						}
					}
					else
					{
						target_node.innerHTML = "Empty";
					}
				}
				else
				{
					target_node.innerHTML = target_node.dataset.name;
				}
			}
		} );
	 }
	 else
	 {
		alert( "You must select a content item to update." );
	 }
}

function RemoveGroup()
{
	if ( selected_nodes != null && selected_nodes.length > 0 )
	{
		var node_list = { "nodes": [], "ids": [] };

		for ( var i = 0; i < selected_nodes.length; ++i )
		{
			if ( selected_nodes[ i ].dataset.type == ITEM_TYPE.GROUP )
			{
				// Exclude child groups if their ancestor is selected.
				if ( selected_nodes[ i ].parentNode.parentNode.closest( ".selected" ) == null )
				{
					node_list.nodes.push( selected_nodes[ i ] );
					node_list.ids.push( selected_nodes[ i ].dataset.id );
				}
			}
		}

		if ( node_list.nodes.length > 0 )
		{
			DB_RemoveGroup( node_list.ids ).then( function( remove_group_ret )
			{
				if ( remove_group_ret != -1 )	// DB_RemoveGroup was successful.
				{
					if ( focused_node != null )
					{
						focused_node.classList.remove( "focused" );
						focused_node = null;
					}

					for ( var i = 0; i < node_list.nodes.length; ++i )
					{
						node_list.nodes[ i ].parentNode.removeChild( node_list.nodes[ i ] );
					}
				}
			} ).then( () =>
			{
				ungrouped_content_root_node.lastElementChild.innerHTML = "";

				GetUngroupedContent();
			} );
		}
		else
		{
			alert( "No group items were selected for removal." );
		}
	}
	else
	{
		alert( "You must select a group item to remove." );
	}
}

function RemoveContent()
{
	if ( selected_nodes != null && selected_nodes.length > 0 )
	{
		var node_list = { "nodes": [], "ids": [], "number_nodes": [] };

		if ( !g_order_channels )
		{
			for ( var i = 0; i < selected_nodes.length; ++i )
			{
				if ( selected_nodes[ i ].dataset.type == ITEM_TYPE.CONTENT )
				{
					node_list.nodes.push( selected_nodes[ i ] );
					node_list.ids.push( selected_nodes[ i ].dataset.id );
				}
			}
		}
		else
		{
			var node_count = channel_numbers_root_node.childNodes[ 1 ].childNodes[ 1 ].childElementCount;

			for ( var i = 0; i < node_count; ++i )
			{
				var node = channel_numbers_root_node.childNodes[ 1 ].childNodes[ 1 ].childNodes[ i ];

				if ( node.classList.contains( "selected" ) && node.dataset.type == ITEM_TYPE.CONTENT )
				{
					node_list.nodes.push( node );
					node_list.ids.push( node.dataset.id );
					node_list.number_nodes.push( channel_numbers_root_node.childNodes[ 1 ].childNodes[ 0 ].childNodes[ i ] );
				}
			}
		}

		if ( node_list.nodes.length > 0 )
		{
			DB_RemoveContent( node_list.ids ).then( function( remove_content_ret )
			{
				if ( remove_content_ret != -1 )	// DB_RemoveContent was successful.
				{
					for ( var i = 0; i < node_list.nodes.length; ++i )
					{
						if ( !g_order_channels )
						{
							var target_node = node_list.nodes[ i ];

							// Decrease the content count from the node's group.
							var parent_node = target_node.parentNode.parentNode;
							--parent_node.dataset.content_count;
							var content_count_string = ( parent_node.dataset.content_count > 0 ? " (" + parent_node.dataset.content_count + ")" : "" );
							if ( parent_node.dataset.name == "" )
							{
								if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && parent_node.dataset.group_type == 2 )
								{
									parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Season " + parent_node.dataset.group_season + content_count_string;
								}
								else
								{
									parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = "Empty" + content_count_string;
								}
							}
							else
							{
								parent_node.childNodes[ 0 ].childNodes[ 1 ].innerHTML = parent_node.dataset.name + content_count_string;
							}

							parent_node.lastElementChild.removeChild( target_node );
						}
						else
						{
							node_list.nodes[ i ].parentNode.removeChild( node_list.nodes[ i ] );
							node_list.number_nodes[ i ].parentNode.removeChild( node_list.number_nodes[ i ] );
						}
					}
				}
			} );
		}
		else
		{
			alert( "No content items were selected for removal." );
		}
	 }
	 else
	 {
		alert( "You must select a content item to remove." );
	 }
}

function GetRequest( group_node )
{
	var group_id = ( group_node != null ? group_node.dataset.id : 0 );

	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				var tmp_node = document.createElement( "div" );

				var box = ( group_node != null ? group_node.lastElementChild : groups_and_content_root_node.lastElementChild );

				var json = JSON.parse( this.responseText );

				var type = json.data.type;
				var values = json.data.values;
				values.forEach( ( item ) =>
				{
					if ( type == ITEM_TYPE.CONTENT )
					{
						var div = document.createElement( "div" );

						div.dataset.type = type;
						div.dataset.id = item.id;
						div.dataset.name = item.name;

						div.dataset.guide_name = ( item.guide_name != undefined ? item.guide_name : "" );
						div.dataset.season = ( item.season != undefined ? item.season : "" );
						div.dataset.episode = ( item.episode != undefined ? item.episode : "" );
						div.dataset.year = ( item.year != undefined ? item.year : "" );
						div.dataset.extension = ( item.extension != undefined ? item.extension : "" );
						div.dataset.subtitle_url = ( item.subtitle_url != undefined ? item.subtitle_url : "" );
						div.dataset.url = ( item.url != undefined ? item.url : "" );
						div.dataset.logo_url = ( item.logo_url != undefined ? item.logo_url : "" );
						div.dataset.headers = ( item.headers != undefined ? item.headers : "" );

						if ( div.dataset.name == "" )
						{
							if ( g_database_type == DB_TYPE.VOD_TV_SHOWS && group_node != null && group_node.dataset.group_type == 2 )
							{
								div.innerHTML = "Episode " + div.dataset.episode;
							}
							else
							{
								div.innerHTML = "Season " + div.dataset.season + " - Episode " + div.dataset.episode;
							}
						}
						else
						{
							div.innerHTML = div.dataset.name;
						}

						div.classList.add( "box" );
						div.draggable = true;

						tmp_node.appendChild( div );
					}
				} );

				box.append( ...tmp_node.childNodes );

				resolve( 1 );
			}
		};

		xmlhttp.open( "GET", g_request_path + "/get_content.php?type=" + g_database_type + "&id=" + group_id + "&limit=-1&get_guide_name=true", true );
		xmlhttp.send();
	} );

	return p;
}

function DB_GetAllGroups( id )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "id", id );

		xmlhttp.open( "POST", g_manager_request_path + "/get_all_groups.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_GetUngroupedContent()
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		xmlhttp.open( "GET", g_manager_request_path + "/get_ungrouped_content.php?type=" + g_database_type, true );
		xmlhttp.send();
	} );

	return p;
}

function DB_AddGroup( parent_id, data )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "parent_id", parent_id );
		form_data.append( "add_type", "0" );	// Add Group
		form_data.append( "name", data.name );
		form_data.append( "group_type", data.group_type );
		form_data.append( "group_year", data.group_year );
		form_data.append( "group_season", data.group_season );

		xmlhttp.open( "POST", g_manager_request_path + "/add_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_AddContent( parent_id, data )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "parent_id", parent_id );
		form_data.append( "add_type", "1" );	// Add Content
		form_data.append( "name", data.name );
		form_data.append( "guide_name", data.guide_name );
		form_data.append( "season", data.season );
		form_data.append( "episode", data.episode );
		form_data.append( "year", data.year );
		form_data.append( "extension", data.extension );
		form_data.append( "logo_url", data.logo_url );
		form_data.append( "subtitle_url", data.subtitle_url );
		form_data.append( "url", data.url );
		form_data.append( "headers", data.headers );

		xmlhttp.open( "POST", g_manager_request_path + "/add_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_UpdateContent( id, data )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "2" );	// Content update.
		form_data.append( "id", id );
		form_data.append( "name", data.name );
		form_data.append( "guide_name", data.guide_name );
		form_data.append( "season", data.season );
		form_data.append( "episode", data.episode );
		form_data.append( "year", data.year );
		form_data.append( "extension", data.extension );
		form_data.append( "logo_url", data.logo_url );
		form_data.append( "subtitle_url", data.subtitle_url );
		form_data.append( "url", data.url );
		form_data.append( "headers", data.headers );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_UpdateGroup( id, data )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "0" );	// Group name update.
		form_data.append( "id", id );
		form_data.append( "name", data.name );
		form_data.append( "group_type", data.group_type );
		form_data.append( "group_year", data.group_year );
		form_data.append( "group_season", data.group_season );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_RemoveContent( ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "5" );	// Content remove.
		form_data.append( "ids", JSON.stringify( ids ) );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_RemoveGroup( ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "4" );	// Group remove.
		form_data.append( "ids", JSON.stringify( ids ) );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_MoveContent( id, ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "3" );	// Content move.
		form_data.append( "id", id );
		form_data.append( "ids", JSON.stringify( ids ) );	// This is an array of associative arrays.

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_MoveGroup( id, ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "1" );	// Group move.
		form_data.append( "id", id );
		form_data.append( "ids", JSON.stringify( ids ) );	// This is an array of associative arrays.

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_GetAllChannels()
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				var tmp_numbers_node = document.createElement( "div" );
				var tmp_node = document.createElement( "div" );

				var numbers_box = channel_numbers_root_node.lastElementChild.firstElementChild;
				var box = channel_numbers_root_node.lastElementChild.lastElementChild;

				var json = JSON.parse( this.responseText );

				var type = json.data.type;
				var values = json.data.values;
				values.forEach( ( item ) =>
				{
					if ( type == ITEM_TYPE.CONTENT )
					{
						var div_numbers = document.createElement( "div" );
						div_numbers.classList.add( "box" );
						div_numbers.classList.add( "channel_numbers_numbers" );
						div_numbers.draggable = false;

						div_numbers.innerHTML = item.number;

						tmp_numbers_node.appendChild( div_numbers );

						//

						var div = document.createElement( "div" );
						div.classList.add( "box" );
						div.classList.add( "channel_numbers_channels" );
						div.draggable = true;

						div.innerHTML = item.name;

						div.dataset.type = type;
						div.dataset.id = item.id;
						div.dataset.name = item.name;

						div.dataset.guide_name = ( item.guide_name != undefined ? item.guide_name : "" );
						div.dataset.season = ( item.season != undefined ? item.season : "" );
						div.dataset.episode = ( item.episode != undefined ? item.episode : "" );
						div.dataset.year = ( item.year != undefined ? item.year : "" );
						div.dataset.extension = ( item.extension != undefined ? item.extension : "" );
						div.dataset.subtitle_url = ( item.subtitle_url != undefined ? item.subtitle_url : "" );
						div.dataset.url = ( item.url != undefined ? item.url : "" );
						div.dataset.logo_url = ( item.logo_url != undefined ? item.logo_url : "" );
						div.dataset.headers = ( item.headers != undefined ? item.headers : "" );

						tmp_node.appendChild( div );
					}
				} );

				numbers_box.append( ...tmp_numbers_node.childNodes );
				box.append( ...tmp_node.childNodes );

				resolve( 1 );
			}
		};

		xmlhttp.open( "GET", g_manager_request_path + "/get_channel_list.php", true );
		xmlhttp.send();
	} );

	return p;
}

function DB_SaveChannelNumbers( ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "8" );	// Save Channel Numbers.
		form_data.append( "ids", JSON.stringify( ids ) );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function SortChannelNumbers()
{
	var child_nodes = channel_numbers_root_node.childNodes[ 1 ].childNodes[ 1 ].childNodes;

	// No point in sorting 1 or less items.
	if ( child_nodes.length > 1 )
	{
		[...child_nodes].sort( ( a, b ) => a.dataset.name.localeCompare( b.dataset.name, "en", { ignorePunctuation: false } ) ).forEach( node => channel_numbers_root_node.childNodes[ 1 ].childNodes[ 1 ].appendChild( node ) );
	}
}

function ApplyOrder()
{
	var ids = [];

	for ( const channel of channel_numbers_root_node.lastElementChild.lastElementChild.childNodes )
	{
		ids.push( channel.dataset.id );
	}

	DB_SaveChannelNumbers( ids );
}

function OrderChildren()
{
	g_allow_insert = UI_order_children.checked;
}

function SortByName()
{
	var target_node = null;

	if ( focused_node != null )
	{
		if ( focused_node.dataset.type == ITEM_TYPE.GROUP && focused_node.classList.contains( "selected" ) )
		{
			target_node = focused_node;
		}
	}
	else
	{
		target_node = groups_and_content_root_node;
	}

	if ( target_node != null )
	{
		var child_nodes = target_node.lastElementChild.childNodes;

		// No point in sorting 1 or less items.
		if ( child_nodes.length > 1 )
		{
			[...child_nodes].sort( ( a, b ) => a.dataset.name.localeCompare( b.dataset.name, "en", { ignorePunctuation: true } ) ).forEach( node => target_node.lastElementChild.appendChild( node ) );

			var ids = [];

			for ( const node of child_nodes )
			{
				ids.push( node.dataset.id );
			}

			if ( target_node.dataset.content_count == 0 )	// Sort groups.
			{
				DB_SaveGroupOrder( ids ).then( function( save_group_order_ret )
				{
					if ( save_group_order_ret != 1 )	// DB_SaveGroupOrder was successful.
					{
						alert( "Group order was not saved." );
					}
				} );
			}
			else	// Sort content.
			{
				DB_SaveContentOrder( ids ).then( function( save_content_order_ret )
				{
					if ( save_content_order_ret != 1 )	// DB_SaveContentOrder was successful.
					{
						alert( "Content order was not saved." );
					}
				} );
			}
		}
	}
}

function DB_SaveGroupOrder( ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "6" );	// Save Group Order.
		form_data.append( "ids", JSON.stringify( ids ) );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function DB_SaveContentOrder( ids )
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				resolve( this.responseText );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", g_database_type );
		form_data.append( "save_type", "7" );	// Save Content Order.
		form_data.append( "ids", JSON.stringify( ids ) );

		xmlhttp.open( "POST", g_manager_request_path + "/save_content.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

/*
function RetrieveInfo()
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				var json = JSON.parse( this.responseText );

				if ( json.data.year != 0 )
				{
					UI_year.value = json.data.year;
				}

				if ( json.data.logo_url != "" )
				{
					UI_poster_URL.value = json.data.logo_url;
				}

				if ( focused_node != null && focused_node.dataset.type == ITEM_TYPE.CONTENT )
				{
					focused_node.dataset.year = UI_year.value;
					focused_node.dataset.logo_url = UI_poster_URL.value;
				}

				resolve( 1 );
			}
		};

		xmlhttp.open( "GET", g_request_path + "/get_content_info.php?name=" + encodeURIComponent( UI_content_name.value ) + "&year=" + encodeURIComponent( UI_year.value ), true );
		xmlhttp.send();
	} );

	return p;
}
*/

function handleGroupType( e )
{
	if ( e.target.matches( "input[type='radio']" ) )
	{
		UI_group_year.parentNode.parentNode.style.display = ( e.target.value == 0 ? "none" : "block" );
		UI_group_season.parentNode.parentNode.style.display = ( e.target.value == 2 ? "block" : "none" );

		if ( e.target.value < 2 )
		{
			UI_group_season.value = "";
		}

		if ( e.target.value < 1 )
		{
			UI_group_year.value = "";
		}
	}
}

function SetGroupType( type )
{
	if ( type == 1 )
	{
		UI_group_type_1.click();
	}
	else if ( type == 2 )
	{
		UI_group_type_2.click();
	}
	else
	{
		UI_group_type_0.click();
	}
}

function GetGroupType()
{
	if ( UI_group_type_1.checked )
	{
		return 1;
	}
	else if ( UI_group_type_2.checked )
	{
		return 2;
	}
	else
	{
		return 0;
	}
}

function CreateDatabases()
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				var json = JSON.parse( this.responseText );

				var elapsed_time = parseFloat( json.elapsed_time ).toFixed( 2 );
				alert( "Created databases in " + elapsed_time + " seconds." );

				resolve( 1 );
			}
		};

		xmlhttp.open( "GET", g_manager_request_path + "/create_databases.php", true );
		xmlhttp.send();
	} );

	return p;
}

function ProcessData( data_type )
{
	var p = new Promise( function( resolve, reject )
	{
		if ( data_type >= 0 && data_type <= 3 )
		{
			var script_path = "";

			switch ( data_type )
			{
				case 0: { script_path = "/process_playlist.php"; } break;
				case 1: { script_path = "/process_vod_movies.php"; } break;
				case 2: { script_path = "/process_vod_series.php"; } break;
				case 3: { script_path = "/process_epg.php"; } break;
			}

			const xmlhttp = new XMLHttpRequest();
			xmlhttp.onreadystatechange = function()
			{
				if ( this.readyState == 4 && this.status == 200 )
				{
					var json = JSON.parse( this.responseText );

					var elapsed_time = parseFloat( json.elapsed_time ).toFixed( 2 );
					alert( "Processed data in " + elapsed_time + " seconds." + "\r\n\r\nTotal items: " + json.total_items + "\r\nTotal items added: " + json.total_items_added );

					resolve( 1 );
				}
			};

			xmlhttp.open( "GET", g_manager_request_path + script_path, true );
			xmlhttp.send();
		}
		else
		{
			resolve( 0 );
		}
	} );

	return p;
}

function DownloadURL( data_type )
{
	var p = new Promise( function( resolve, reject )
	{
		var url = "";
		if ( data_type == 0 )
		{
			url = document.getElementById( "playlist_url" ).value;
		}
		else if ( data_type == 1 )
		{
			url = document.getElementById( "epg_url" ).value;
		}

		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				var json = JSON.parse( this.responseText );

				if ( json.status == 1 )
				{
					var elapsed_time = parseFloat( json.elapsed_time ).toFixed( 2 );
					alert( "Downloaded URL in " + elapsed_time + " seconds." + "\r\n\r\nTotal downloaded: " + json.total + " bytes" );
				}
				else
				{
					alert( "URL was not downloaded." );
				}

				resolve( 1 );
			}
		};

		var form_data = new FormData();
		form_data.append( "type", data_type );
		form_data.append( "url", url );

		xmlhttp.open( "POST", g_manager_request_path + "/download_url.php", true );
		xmlhttp.send( form_data );
	} );

	return p;
}

function GetDatabaseInfo()
{
	var p = new Promise( function( resolve, reject )
	{
		const xmlhttp = new XMLHttpRequest();
		xmlhttp.onreadystatechange = function()
		{
			if ( this.readyState == 4 && this.status == 200 )
			{
				var json = JSON.parse( this.responseText );

				var data_info = document.getElementById( "data_info" );

				var info_str = "";
				if ( json.channels_modification_time == 0 )
				{
					info_str += "channels.db not created.";
				}
				else
				{
					info_str += "channels.db last modified on: " + new Date( json.channels_modification_time * 1000 ).toLocaleString();
				}
				info_str += "<br>";
				if ( json.vod_modification_time == 0 )
				{
					info_str += "vod.db not created.";
				}
				else
				{
					info_str += "vod.db last modified on: " + new Date( json.vod_modification_time * 1000 ).toLocaleString();
				}
				info_str += "<br>";
				if ( json.playlist_modification_time == 0 )
				{
					info_str += "playlist.m3u not created.";
				}
				else
				{
					info_str += "playlist.m3u last modified on: " + new Date( json.playlist_modification_time * 1000 ).toLocaleString();
				}
				info_str += "<br>";
				if ( json.epg_modification_time == 0 )
				{
					info_str += "epg.xml not created.";
				}
				else
				{
					info_str += "epg.xml last modified on: " + new Date( json.epg_modification_time * 1000 ).toLocaleString();
				}

				data_info.innerHTML = info_str;

				resolve( 1 );
			}
		};

		xmlhttp.open( "GET", g_manager_request_path + "/get_database_info.php", true );
		xmlhttp.send();
	} );

	return p;
}

document.addEventListener( 'DOMContentLoaded', ( event ) =>
{
	var load_data = document.getElementById( "load_data" );
	load_data.addEventListener( "change", LoadData );
	load_data.selectedIndex = 0;

	UI_channel_controls = document.getElementById( "channel_controls" );
	UI_order_controls = document.getElementById( "order_controls" );

	//

	UI_group_input = document.getElementById( "group_input" );

	UI_group_name = document.getElementById( "group_name" );

	UI_group_type_0 = document.getElementById( "group_type_0" );
	UI_group_type_1 = document.getElementById( "group_type_1" );
	UI_group_type_2 = document.getElementById( "group_type_2" );
	UI_group_year = document.getElementById( "group_year" );
	UI_group_season = document.getElementById( "group_season" );

	UI_group_type_0.parentNode.addEventListener( 'click', handleGroupType );
	SetGroupType( 0 );

	//

	UI_content_input = document.getElementById( "content_input" );

	UI_content_name = document.getElementById( "content_name" );
	UI_guide_name = document.getElementById( "guide_name" );
	UI_season = document.getElementById( "season" );
	UI_episode = document.getElementById( "episode" );
	UI_year = document.getElementById( "year" );
	UI_URL = document.getElementById( "url" );
	UI_stream_type = document.getElementById( "stream_type" );
	UI_subtitle_URL = document.getElementById( "subtitle_url" );
	UI_poster_URL = document.getElementById( "poster_url" );
	UI_headers = document.getElementById( "headers" );

	//

	UI_order_children = document.getElementById( "order_children" );

	//

	var container = document.getElementById( "container" );

	//

	channel_numbers = document.getElementById( "channel_numbers" );
	channel_numbers.style.display = "none";

		channel_numbers_root_node = document.createElement( "div" );
		channel_numbers_root_node.classList.add( "group" );
		channel_numbers_root_node.classList.add( "box" );
		channel_numbers_root_node.draggable = false;

		channel_numbers_root_node.dataset.type = ITEM_TYPE.GROUP;
		channel_numbers_root_node.dataset.id = 0;
		channel_numbers_root_node.dataset.parent_id = 0;
		channel_numbers_root_node.dataset.content_count = 0
		channel_numbers_root_node.dataset.expanded = 1;
		channel_numbers_root_node.dataset.name = "[CHANNEL LIST]";
		channel_numbers_root_node.dataset.group_type = 0;
		channel_numbers_root_node.dataset.group_year = 0;
		channel_numbers_root_node.dataset.group_season = 0;

		var group_name = document.createElement( "div" );
		group_name.classList.add( "group_name" );
		group_name.style.textAlign = "center";
		group_name.innerHTML = "[CHANNEL LIST]";

		var sub_group = document.createElement( "div" );
		sub_group.classList.add( "channel_numbers" );
	
			var sub_group1 = document.createElement( "div" );
			var sub_group2 = document.createElement( "div" );

			sub_group.appendChild( sub_group1 );
			sub_group.appendChild( sub_group2 );

		channel_numbers_root_node.appendChild( group_name );
		channel_numbers_root_node.appendChild( sub_group );

	channel_numbers.appendChild( channel_numbers_root_node );

	//

	groups_and_content = document.getElementById( "groups_and_content" );
	groups_and_content.style.display = "none";

		groups_and_content_root_node = document.createElement( "div" );
		groups_and_content_root_node.id = "g0";
		groups_and_content_root_node.classList.add( "group" );
		groups_and_content_root_node.classList.add( "box" );
		groups_and_content_root_node.classList.add( "selected_container" );
		groups_and_content_root_node.draggable = false;

		groups_and_content_root_node.dataset.type = ITEM_TYPE.GROUP;
		groups_and_content_root_node.dataset.id = 0;
		groups_and_content_root_node.dataset.parent_id = 0;
		groups_and_content_root_node.dataset.content_count = 0
		groups_and_content_root_node.dataset.expanded = 1;
		groups_and_content_root_node.dataset.name = "[ROOT MENU]";
		groups_and_content_root_node.dataset.group_type = 0;
		groups_and_content_root_node.dataset.group_year = 0;
		groups_and_content_root_node.dataset.group_season = 0;

		var group_name = document.createElement( "div" );
		group_name.classList.add( "group_name" );
		group_name.style.textAlign = "center";
		group_name.innerHTML = "[ROOT MENU]";

		var sub_group = document.createElement( "div" );

		groups_and_content_root_node.appendChild( group_name );
		groups_and_content_root_node.appendChild( sub_group );

	groups_and_content.appendChild( groups_and_content_root_node );

	focused_container = groups_and_content_root_node;

	//

	ungrouped_content = document.getElementById( "ungrouped_content" );
	ungrouped_content.style.display = "none";

		ungrouped_content_root_node = document.createElement( "div" );
		ungrouped_content_root_node.classList.add( "group" );
		ungrouped_content_root_node.classList.add( "box" );
		ungrouped_content_root_node.draggable = false;

		ungrouped_content_root_node.dataset.type = ITEM_TYPE.GROUP;
		ungrouped_content_root_node.dataset.id = -1;
		ungrouped_content_root_node.dataset.parent_id = -1;
		ungrouped_content_root_node.dataset.content_count = 0;
		ungrouped_content_root_node.dataset.expanded = 1;
		ungrouped_content_root_node.dataset.name = "[UNGROUPED CONTENT]";
		ungrouped_content_root_node.dataset.group_type = 0;
		ungrouped_content_root_node.dataset.group_year = 0;
		ungrouped_content_root_node.dataset.group_season = 0;

		var group_info = document.createElement( "div" );
		group_info.style.textAlign = "center";

			var group_expand = document.createElement( "div" );
			group_expand.classList.add( "group_expand" );

			var group_name = document.createElement( "div" );
			group_name.classList.add( "group_name" );
			group_name.innerHTML = "[UNGROUPED CONTENT]";

			group_info.appendChild( group_expand );
			group_info.appendChild( group_name );

		var sub_group = document.createElement( "div" );

		ungrouped_content_root_node.appendChild( group_info );
		ungrouped_content_root_node.appendChild( sub_group );

	ungrouped_content.appendChild( ungrouped_content_root_node );

	container.addEventListener( 'dragstart', handleDragStart );
	container.addEventListener( 'dragover', handleDragOver );
	container.addEventListener( 'dragleave', handleDragLeave );
	container.addEventListener( 'drop', handleDrop );
	container.addEventListener( 'dragend', handleDragEnd );
	container.addEventListener( 'click', handleSelection );

	GetDatabaseInfo();
} );
