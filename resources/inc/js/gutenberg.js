(function(plugins, editPost, element, components, compose, data) {
	const el = element.createElement;
	const { registerPlugin } = plugins;
	const { PluginPostStatusInfo } = editPost;
	const { CheckboxControl } = components;
    const { withSelect } = data;

    var MetaTextControl = compose.compose(
        withSelect(function(select, props) {
            return {
                metaValue: select('core/editor').getEditedPostAttribute('meta')[props.metaKey],
            }
        }))(function(props) {
            let [isChecked, setChecked] = element.useState(false);

            return el(CheckboxControl, {
                    metaKey: '_static_output_html',
                    label: 'Gerar página estática',
                    checked: isChecked,
                    onChange: () => {
                        isChecked = setChecked(!isChecked)
                        wp.data.dispatch('core/editor').editPost({meta: {_static_output_html: !isChecked}})
                    },
                } 
            );
        }
    );
    
    const Output = () => {
        return el(PluginPostStatusInfo, {},
            el(MetaTextControl)
        );
    }

	registerPlugin('relation-output', { render: Output });
}) (
	window.wp.plugins,
	window.wp.editPost,
	window.wp.element,
	window.wp.components,
    window.wp.compose,
    window.wp.data,
);