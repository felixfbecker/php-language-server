<?php

/**
 * @since 8.1
 */
abstract class UnitEnum
{
    public readonly string $name;

    /**
     * Generates a list of cases on an enum
     * @return static[] An array of all defined cases of this enumeration, in lexical order.
     */
    #[Pure]
    public static function cases(): array {}
}

/**
 * @since 8.1
 */
abstract class BackedEnum extends UnitEnum
{
    public readonly int|string $value;

    /**
     * Maps a scalar to an enum instance
     * @param int|string $value The scalar value to map to an enum case.
     * @return static A case instance of this enumeration.
     */
    #[Pure]
    public static function from(int|string $value): static {}

    /**
     * Maps a scalar to an enum instance or null
     * @param int|string $value The scalar value to map to an enum case.
     * @return static|null A case instance of this enumeration, or null if not found.
     */
    #[Pure]
    public static function tryFrom(int|string $value): ?static {}
}

/**
 * @since 8.1
 * @internal
 *
 * Internal interface to ensure precise type inference
 */
abstract class IntBackedEnum extends UnitEnum
{
    public readonly int $value;

    /**
     * Maps a scalar to an enum instance
     * @param int $value The scalar value to map to an enum case.
     * @return static A case instance of this enumeration.
     */
    #[Pure]
    public static function from(int $value): static {}

    /**
     * Maps a scalar to an enum instance or null
     * @param int $value The scalar value to map to an enum case.
     * @return static|null A case instance of this enumeration, or null if not found.
     */
    #[Pure]
    public static function tryFrom(int $value): ?static {}
}

/**
 * @since 8.1
 * @internal
 *
 * Internal interface to ensure precise type inference
 */
abstract class StringBackedEnum extends UnitEnum
{
    public readonly string $value;

    /**
     * Maps a scalar to an enum instance
     * @param string $value The scalar value to map to an enum case.
     * @return static A case instance of this enumeration.
     */
    #[Pure]
    public static function from(string $value): static {}

    /**
     * Maps a scalar to an enum instance or null
     * @param string $value The scalar value to map to an enum case.
     * @return static|null A case instance of this enumeration, or null if not found.
     */
    #[Pure]
    public static function tryFrom(string $value): ?static {}
}
