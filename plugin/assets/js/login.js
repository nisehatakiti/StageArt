( function () {
	'use strict';

	var form = document.getElementById( 'stageart-login-form' );

	if ( ! form || typeof StageArtLogin === 'undefined' ) {
		return;
	}

	var errorEl = document.getElementById( 'stageart-login-error' );

	form.addEventListener( 'submit', function ( event ) {
		event.preventDefault();

		errorEl.hidden = true;
		errorEl.textContent = '';

		var submitButton = form.querySelector( '.stageart-login__submit' );
		submitButton.disabled = true;

		fetch( StageArtLogin.restUrl, {
			method: 'POST',
			headers: {
				'Content-Type': 'application/json',
				'X-WP-Nonce': StageArtLogin.nonce
			},
			body: JSON.stringify( {
				email: form.email.value,
				password: form.password.value
			} )
		} )
			.then( function ( response ) {
				return response.json().then( function ( data ) {
					return { ok: response.ok, data: data };
				} );
			} )
			.then( function ( result ) {
				if ( ! result.ok ) {
					var errors = result.data && result.data.errors;
					var message = errors && errors[ 0 ] && errors[ 0 ].message;
					throw new Error( message || 'ログインに失敗しました。' );
				}

				window.location.href = ( result.data.data && result.data.data.redirectUrl ) || '/';
			} )
			.catch( function ( error ) {
				errorEl.textContent = error.message;
				errorEl.hidden = false;
			} )
			.finally( function () {
				submitButton.disabled = false;
			} );
	} );
}() );
