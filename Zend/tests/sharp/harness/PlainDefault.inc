<?php

// Plain PHP still writes no new in a property's initial value: only a PHP# Map or Set field starts as an object.
class PlainDefault
{
    public Sharp\Map $prices = new Sharp\Map();
}
