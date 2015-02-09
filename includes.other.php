<?php

function cart_sort_order( $cart, $man=false, $type=false, $retail=false, $user=false, $concat=false ){
	$order = <<<HEREDOC
	{$cart} LIKE '%OEM%' ASC, 
	{$type} LIKE '%Black Ink%' DESC, 
	{$type} LIKE '%Black Tone%' DESC, 
	{$type} LIKE '%Black Pigmented Ink%' DESC, 
	{$type} LIKE '%Black UV Ink%' DESC, 
	{$type} LIKE '%Black%' DESC, 
	{$type} LIKE '%Tricolor%' DESC,
	{$type} LIKE '%Light%' ASC,
	{$type} LIKE '%Cyan Ink%' DESC, 
	{$type} LIKE '%Cyan Tone%' DESC, 
	{$type} LIKE '%Cyan Pigmented Ink%' DESC, 
	{$type} LIKE '%Cyan UV Ink%' DESC, 
	{$type} LIKE '%Magenta Ink%' DESC, 
	{$type} LIKE '%Magenta Tone%' DESC, 
	{$type} LIKE '%Magenta Pigmented Ink%' DESC, 
	{$type} LIKE '%Magenta UV Ink%' DESC, 
	{$type} LIKE '%Magenta%' DESC, 
	{$type} LIKE '%Yellow Ink%' DESC, 
	{$type} LIKE '%Yellow Tone%' DESC, 
	{$type} LIKE '%Yellow Pigmented Ink%' DESC, 
	{$type} LIKE '%Yellow UV Ink%' DESC, 
	{$type} LIKE '%Yellow%' DESC, 
	{$type} ASC,
	{$cart} ASC
HEREDOC;

	return $order;
}

?>