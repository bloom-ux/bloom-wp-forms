/**
 * Componente de edición del bloque "Formulario".
 *
 * Muestra un placeholder con la caja punteada nativa del editor: primero el
 * selector del tipo de formulario (los formularios activos llegan en
 * window.bloomFormsData), luego un resumen con acceso a cambiar la selección.
 */
import { __ } from '@wordpress/i18n';
import { useState } from '@wordpress/element';
import { useBlockProps } from '@wordpress/block-editor';
import { Button, Placeholder, SelectControl } from '@wordpress/components';

/**
 * Obtener los formularios activos pasados por el servidor.
 *
 * @return {Array} Lista de { slug, title }.
 */
const getForms = () => window.bloomFormsData?.forms ?? [];

/**
 * Componente de edición.
 *
 * @param {Object}   props               Propiedades del bloque.
 * @param {Object}   props.attributes    Atributos del bloque.
 * @param {Function} props.setAttributes Setter de atributos.
 * @return {Element} Elemento de React.
 */
export default function Edit( { attributes, setAttributes } ) {
	const { form_slug: formSlug } = attributes;
	const forms = getForms();
	const selectedForm = forms.find( ( form ) => form.slug === formSlug );
	const [ isSelecting, setIsSelecting ] = useState( ! formSlug );

	if ( selectedForm && ! isSelecting ) {
		return (
			<div { ...useBlockProps() }>
				<Placeholder
					icon="feedback"
					label={ __( 'Formulario', 'bloom-wp-forms' ) }
				>
					<p>
						<strong>{ selectedForm.title }</strong>{ ' ' }
						<code>{ selectedForm.slug }</code>
					</p>
					<Button
						variant="secondary"
						onClick={ () => setIsSelecting( true ) }
					>
						{ __( 'Cambiar formulario', 'bloom-wp-forms' ) }
					</Button>
				</Placeholder>
			</div>
		);
	}

	return (
		<div { ...useBlockProps() }>
			<Placeholder
				icon="feedback"
				label={ __( 'Formulario', 'bloom-wp-forms' ) }
				instructions={
					forms.length
						? __(
								'Selecciona el tipo de formulario a insertar',
								'bloom-wp-forms'
						  )
						: __( 'No hay formularios activos.', 'bloom-wp-forms' )
				}
			>
				<SelectControl
					label={ __( 'Tipo de formulario', 'bloom-wp-forms' ) }
					value={ formSlug }
					disabled={ ! forms.length }
					options={ [
						{
							label: __( 'Elige un formulario…', 'bloom-wp-forms' ),
							value: '',
						},
						...forms.map( ( form ) => ( {
							label: form.title,
							value: form.slug,
						} ) ),
					] }
					onChange={ ( value ) => {
						setAttributes( { form_slug: value } );
						setIsSelecting( false );
					} }
				/>
			</Placeholder>
		</div>
	);
}
