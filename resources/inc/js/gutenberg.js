(function(plugins, editPost, element, components, compose, data) {
	const el = element.createElement;
	const { registerPlugin } = plugins;
	const { unregisterPlugin } = plugins;
	const { getPlugin } = plugins;
	const { PluginPostStatusInfo } = editPost;
	const { CheckboxControl } = components;
	const { ExternalLink } = components;
    const { withSelect } = data;

    var MetaTextControl = compose.compose(
        withSelect(function(select, props) {
            return {
                metaValue: select('core/editor').getEditedPostAttribute('meta')[props.metaKey]
            }
        }))(function(props) {
            
            let [isChecked, setChecked] = element.useState(true);

            return el(CheckboxControl, {
                    metaKey: '_static_output_html',
                    label: 'Gerar página estática',
                    checked: isChecked,
                    onChange: () => {
                        setChecked(!isChecked);
                        wp.data.dispatch('core/editor').editPost({meta: {_static_output_html: !isChecked}});
                        var meta = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'meta' );
                    },
                }
            );
        }
    );

    const MyExternalLink = () => (
        <ExternalLink href="https://wordpress.org">claudioweb.org</ExternalLink>
    );
    
    const Output = () => {
        return el(PluginPostStatusInfo, {},
            el(MetaTextControl, MyExternalLink)
        );
    }

    function isSavingPost() {

        // State data necessary to establish if a save is occuring.
        const isSaving = wp.data.select('core/editor').isSavingPost() || wp.data.select('core/editor').isAutosavingPost();
        const isSaveable = wp.data.select('core/editor').isEditedPostSaveable();
        const isPostSavingLocked = wp.data.select('core/editor').isPostSavingLocked();
        const hasNonPostEntityChanges = wp.data.select('core/editor').hasNonPostEntityChanges();
        const isAutoSaving = wp.data.select('core/editor').isAutosavingPost();
        const isButtonDisabled = isSaving || !isSaveable || isPostSavingLocked;
        
        // Reduces state into checking whether the post is saving and that the save button is disabled.
        const isBusy = !isAutoSaving && isSaving;
        const isNotInteractable = isButtonDisabled && ! hasNonPostEntityChanges;
        
        return isBusy && isNotInteractable;
    }
    // Current saving state. isSavingPost is defined above.
    var wasSaving = isSavingPost();

    wp.data.subscribe( function () {

        // New saving state
        let isSaving = isSavingPost();

        // It is done saving if it was saving and it no longer is.
        let isDoneSaving = wasSaving && !isSaving;

        // Update value for next use.
        wasSaving = isSaving;

        var old_format = wp.data.select( 'core/editor' ).getCurrentPostAttribute('status');
        var newFormat = wp.data.select( 'core/editor' ).getEditedPostAttribute( 'status' );
        var plugin_relation = getPlugin('relation-output');
        

        if(newFormat=='publish' && typeof plugin_relation=='undefined' || isDoneSaving){
            if(old_format!='auto-draft'){
 			    wp.data.dispatch('core/editor').editPost({meta: {_static_output_html: true}});
            }
            unregisterPlugin('relation-output');
            registerPlugin('relation-output', { render: Output });

        }else if(newFormat!='publish' && typeof plugin_relation!='undefined'){
            unregisterPlugin('relation-output');
        }

        // links
        // var permalink = wp.data.select( 'core/editor' ).getPermalink();
    });

}) (
	window.wp.plugins,
	window.wp.editPost,
	window.wp.element,
	window.wp.components,
    window.wp.compose,
    window.wp.data,
);