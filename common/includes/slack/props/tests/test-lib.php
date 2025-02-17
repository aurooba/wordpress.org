<?php

namespace Dotorg\Slack\Props\Tests;
use wpdbStub;
use PHPUnit\Framework\TestCase;
use function Dotorg\Slack\Props\{ is_valid_props, get_recipient_slack_ids, map_slack_users_to_wporg, prepare_message };

/**
 * @group slack
 * @group props
 */
class Test_Props_Lib extends TestCase {
	public static function setUpBeforeClass() : void {
		require_once dirname( __DIR__ ) . '/lib.php';
	}

	protected static function get_valid_request() {
		$json = file_get_contents( __DIR__ . '/valid-request.json' );

		return json_decode( $json );
	}

	/**
	 * @covers ::is_valid_props
	 * @dataProvider data_is_valid_props
	 * @group unit
	 */
	public function test_is_valid_props( object $event, bool $expected ) : void {
		$actual = is_valid_props( $event );

		$this->assertSame( $expected, $actual );
	}

	public function data_is_valid_props() : array {
		$valid_request = self::get_valid_request();

		$wrong_channel_event = json_decode( json_encode( $valid_request->event ) );
		$wrong_channel_event->channel = 'C01234567';

		$reaction_event = json_decode( json_encode( $valid_request->event ) );
		$reaction_event->type = 'reaction_added';

		$deleted_event = json_decode( json_encode( $valid_request->event ) );
		$deleted_event->subtype = 'message_deleted';

		$hidden_event = json_decode( json_encode( $valid_request->event ) );
		$hidden_event->hidden = true;

		$thread_event = json_decode( json_encode( $valid_request->event ) );
		$thread_event->thread_ts = $valid_request->event->ts;

		$cases = array(
			'missing critical properties' => array(
				'request'  => (object) array( 'foo' => 'bar' ),
				'expected' => false,
			),

			'wrong channel' => array(
				'request'  => $wrong_channel_event,
				'expected' => false,
			),

			'wrong type' => array(
				'request'  => $reaction_event,
				'expected' => false,
			),

			'wrong subtype' => array(
				'request'  => $deleted_event,
				'expected' => false,
			),

			'hidden' => array(
				'request'  => $hidden_event,
				'expected' => false,
			),

			'reply in thread' => array(
				'request'  => $thread_event,
				'expected' => false,
			),

			'valid event' => array(
				'request'  => $valid_request->event,
				'expected' => true,
			),
		);

		return $cases;
	}

	/**
	 * @covers ::get_recipient_slack_ids
	 * @dataProvider data_get_recipient_slack_ids
	 * @group unit
	 */
	public function test_get_recipient_slack_ids( array $blocks, array $expected ) : void {
		$actual = get_recipient_slack_ids( $blocks );

		$this->assertSame( $expected, $actual );
	}

	public function data_get_recipient_slack_ids() : array {
		$valid_request = self::get_valid_request();

		$valid_users = array(
					'U02RR6SGY',
					'U02RQHNND',
					'U3KJ0TK4L',
					'U4L99HZB6',
					'U024MFP4L',
					'U6R2E3Y9Y',
					'U023GFZJ07L',
					'U1E5RLU1L',
				);

		$mentioned_twice = json_decode( json_encode( $valid_request->event->blocks ) );
		$mentioned_twice[0]->elements[0]->elements[] = (object) array(
			'type' => 'text',
			'text' => ' one more time ',
		);
		$mentioned_twice[0]->elements[0]->elements[] = (object) array(
			'type'    => 'user',
			'user_id' => 'U1E5RLU1L',
		);

		$cases = array(
			'empty' => array(
				'blocks'   => array(),
				'expected' => array(),
			),

			'user mentioned twice' => array(
				'blocks'   => $mentioned_twice,
				'expected' => $valid_users,
			),

			'valid' => array(
				'blocks'   => $valid_request->event->blocks,
				'expected' => $valid_users,
			),
		);

		return $cases;
	}

	/**
	 * @covers ::map_slack_users_to_wporg
	 * @dataProvider data_map_slack_users_to_wporg
	 * @group unit
	 */
	public function test_map_slack_users_to_wporg( array $slack_ids, array $db_results, array $expected ) : void {
		global $wpdb;

		$wpdb = $this->createStub( wpdbStub::class );
		$wpdb->method( 'get_results' )->willReturn( $db_results );

		$actual = map_slack_users_to_wporg( $slack_ids );

		$this->assertSame( $expected, $actual );
	}

	public function data_map_slack_users_to_wporg() : array {
		$cases = array(
			'empty' => array(
				'slack_ids'  => array(),
				'db_results' => array(),
				'expected'   => array(),
			),

			'valid giver' => array(
				'slack_ids' => array( 'U02QCF502' ),

				'db_results' => array(
					array(
						'slack_id'   => 'U02QCF502',
						'wporg_id'   => '33690',
						'user_login' => 'iandunn',
					),
				),

				'expected' => array(
					'U02QCF502' => array(
						'id'         => 33690,
						'user_login' => 'iandunn',
					),
				),
			),

			'valid receivers' => array(
				'slack_ids' => array( 'U02RQHNND', 'U02RR6SGY', 'U3KJ0TK4L', 'U4L99HZB6' ),

				'db_results' => array(
					array(
						'slack_id'   => 'U02RQHNND',
						'wporg_id'   => '297445',
						'user_login' => 'SergeyBiryukov',
					),

					array(
						'slack_id'   => 'U02RR6SGY',
						'wporg_id'   => '2255796',
						'user_login' => 'Mamaduka',
					),


					array(
						'slack_id'   => 'U3KJ0TK4L',
						'wporg_id'   => '15049054',
						'user_login' => 'davidbaumwald',
					),

					array(
						'slack_id'   => 'U4L99HZB6',
						'wporg_id'   => '8976791',
						'user_login' => 'pbiron',
					),
				),

				'expected' => array(
					'U02RQHNND' => array(
						'id'         => 297445,
						'user_login' => 'SergeyBiryukov',
					),
					'U02RR6SGY' => array(
						'id'         => 2255796,
						'user_login' => 'Mamaduka',
					),
					'U3KJ0TK4L' => array(
						'id'         => 15049054,
						'user_login' => 'davidbaumwald',
					),

					'U4L99HZB6' => array(
						'id'         => 8976791,
						'user_login' => 'pbiron',
					),
				),
			),
		);

		return $cases;
	}

	/**
	 * @covers ::prepare_message
	 * @dataProvider data_prepare_message
	 * @group unit
	 */
	public function test_prepare_message( string $text, array $user_map, string $expected ) : void {
		$actual = prepare_message( $text, $user_map );

		$this->assertSame( $expected, $actual );
	}

	public function data_prepare_message() : array {
		$valid_request = self::get_valid_request();

		$cases = array(
			'empty' => array(
				'text'     => '',
				'user_map' => array(),
				'expected' => '',
			),

			'valid' => array(
				'text' => $valid_request->event->text,
				'user_map' => array(
					'U023GFZJ07L' => array(
						'id' => 18752239,
						'user_login' => 'costdev',
					),
					'U024MFP4L' => array(
						'id' => 2545,
						'user_login' => 'markjaquith',
					),

					'U02RQHNND' => array(
						'id' => 297445,
						'user_login' => 'SergeyBiryukov',
					),

					'U02RR6SGY' => array(
						'id' => 2255796,
						'user_login' => 'Mamaduka',
					),

					'U1E5RLU1L' => array(
						'id' => 15152479,
						'user_login' => 'jeroenrotty',
					),

					'U3KJ0TK4L' => array(
						'id' => 15049054,
						'user_login' => 'davidbaumwald',
					),

					'U4L99HZB6' => array(
						'id' => 8976791,
						'user_login' => 'pbiron',
					),

					'U6R2E3Y9Y' => array(
						'id' => 15524609,
						'user_login' => 'webcommsat',
					),
				),
				'expected' => 'props to @Mamaduka for co-leading 5.9.3 RC 1, to @SergeyBiryukov for running mission control and to @davidbaumwald @pbiron @markjaquith @webcommsat @costdev @jeroenrotty for their help testing the release package :community: :wordpress:',
			),
		);

		return $cases;
	}
}
