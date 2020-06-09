<?php

namespace TrelloExport;

use League\Csv\Writer;
use Requests;
use Requests_Response;

const API_BASE = 'https://api.trello.com';

require __DIR__ . '/vendor/autoload.php';

/**
 * Get Trello API config.
 *
 * @return array Config array with key and token.
 */
function get_config() : array {
	static $config;
	if ( empty( $config ) ) {
		$config = require __DIR__ . '/config.php';
	}
	return $config;
}

/**
 * Send a request to the Trello API.
 *
 * @param string $endpoint Trello API endpoint.
 * @param array $headers Headers to send (see Requests::request)
 * @param array $data Data to send (see Requests::request)
 * @param array $type HTTP method for request (see Requests::request)
 * @param array $options Options for request (see Requests::request)
 * @throws Requests_Exception
 * @return Requests_Response
 */
function request( string $endpoint, array $headers = [], array $data = [], string $type = Requests::GET, array $options = [] ) : Requests_Response {
	$url = API_BASE . $endpoint;
	$url = add_query_args( $url, get_config() );

	return Requests::request( $url, $headers, $data, $type, $options );
}

/**
 * Add query arguments to a URL.
 *
 * @param string $url Base URL to add arguments to.
 * @param array $args Key => value map of arguments to add (raw, not already encoded).
 * @return string URL with the arguments added.
 */
function add_query_args( string $url, array $args ) : string {
	if ( empty( $args ) ) {
		return $url;
	}

	$parts = [];
	foreach ( $args as $key => $value ) {
		$parts[] = urlencode( $key ) . '=' . urlencode( $value );
	}

	if ( strpos( $url, '?' ) !== false ) {
		$url .= '&' . implode( '&', $parts );
	} else {
		$url .= '?' . implode( '&', $parts );
	}

	return $url;
}

/**
 * Export all comments from a Trello board.
 *
 * @param string $id Board ID.
 * @return void Outputs to the file, and sends messages to stdout.
 */
function export_comments( string $id ) : void {
	$comments_csv = Writer::createFromPath( './comments.tsv', 'w' );
	$comments_csv->setDelimiter( "\t" );
	$comments_csv->insertOne( [
		'ID',
		'CardID',
		'CardName',
		'Date',
		'User',
		'Text'
	] );

	$base_url = sprintf( '/1/boards/%s/actions', $id );
	$per_page = 1000;
	$args = [
		'filter' => 'commentCard',
		'limit' => $per_page,
	];
	$base_url = add_query_args( $base_url, $args );

	$total = 0;
	$cursor = null;
	while ( true ) {
		printf( 'Fetching %d - %d' . PHP_EOL, $total, $total + $per_page );
		if ( $cursor !== null ) {
			$url = add_query_args( $base_url, [
				'before' => $cursor,
			] );
		} else {
			$url = $base_url;
		}

		$resp = request( $url );
		$resp->throw_for_status();

		$data = json_decode( $resp->body );
		foreach ( $data as $row ) {
			$item = [
				$row->id,
				$row->data->card->id,
				$row->data->card->name,
				$row->date,
				! empty( $row->memberCreator ) ? $row->memberCreator->username : 'UNKNOWN_USER',
				$row->data->text,
			];
			$comments_csv->insertOne( $item );
		}

		$total += count( $data );

		// Do we have more pages of data?
		if ( count( $data ) < $per_page ) {
			// Received less than we asked for, so no more pages remaining.
			break;
		}

		// Set the cursor to the last item, and go.
		$cursor = $data[ count( $data ) - 1 ]->id;
	}

	printf( 'OK! Wrote %d entries to %s' . PHP_EOL, $total, $comments_csv->getPathname() );
}

date_default_timezone_set( 'UTC' );

$config = get_config();
$id = $config['id'];
export_comments( $id );
