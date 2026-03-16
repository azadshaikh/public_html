<?php

return [
    'name' => 'SEO',

    /*
    |--------------------------------------------------------------------------
    | URL Types
    |--------------------------------------------------------------------------
    |
    | Defines whether a redirect target is internal (same domain) or external.
    |
    */
    'url_types' => [
        'internal' => [
            'label' => 'Internal',
            'value' => 'internal',
            'description' => 'Redirect to a path on this website (e.g., /new-page)',
        ],
        'external' => [
            'label' => 'External',
            'value' => 'external',
            'description' => 'Redirect to a different website (e.g., https://example.com)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Redirect Types (HTTP Status Codes)
    |--------------------------------------------------------------------------
    |
    | HTTP redirect status codes and their SEO implications.
    |
    */
    'redirect_types' => [
        301 => [
            'label' => '301 Moved Permanently',
            'value' => '301',
            'description' => 'Permanent redirect. Search engines transfer link equity to the new URL. Use for permanent URL changes.',
        ],
        302 => [
            'label' => '302 Found (Temporary)',
            'value' => '302',
            'description' => 'Temporary redirect. Search engines keep indexing the original URL. Use for temporary changes.',
        ],
        307 => [
            'label' => '307 Temporary Redirect',
            'value' => '307',
            'description' => 'Temporary redirect that preserves the request method. Similar to 302 but stricter.',
        ],
        308 => [
            'label' => '308 Permanent Redirect',
            'value' => '308',
            'description' => 'Permanent redirect that preserves the request method. Similar to 301 but stricter.',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Match Types
    |--------------------------------------------------------------------------
    |
    | URL matching strategies for redirect rules.
    |
    */
    'match_types' => [
        'exact' => [
            'label' => 'Exact Match',
            'value' => 'exact',
            'description' => 'URL must match exactly (e.g., /old-page matches only /old-page)',
        ],
        'wildcard' => [
            'label' => 'Wildcard',
            'value' => 'wildcard',
            'description' => 'Use * for single segment, ** for multiple segments (e.g., /blog/* matches /blog/post-1)',
        ],
        'regex' => [
            'label' => 'Regular Expression',
            'value' => 'regex',
            'description' => 'Use regex patterns for complex matching. Captured groups can be used in target URL ($1, $2, etc.)',
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Default Settings
    |--------------------------------------------------------------------------
    */
    'defaults' => [
        'redirect_type' => 301,
        'url_type' => 'internal',
        'match_type' => 'exact',
    ],

    /*
    |--------------------------------------------------------------------------
    | Caching
    |--------------------------------------------------------------------------
    */
    'cache' => [
        'enabled' => true,
        'ttl' => 3600, // 1 hour in seconds
        'key' => 'cms_redirections_active',
    ],

    /*
    |--------------------------------------------------------------------------
    | Safety Limits
    |--------------------------------------------------------------------------
    */
    'limits' => [
        'max_chain_hops' => 10, // Maximum redirect chain length
        'max_url_length' => 1024, // Maximum URL length in characters
    ],

    /*
    |--------------------------------------------------------------------------
    | Local SEO Business Types
    |--------------------------------------------------------------------------
    |
    | Schema.org business types for local SEO structured data.
    | Value is the Schema.org @type, Label is the human-readable name.
    | Reference: https://schema.org/LocalBusiness
    |
    */
    'business_types' => [
        ['value' => 'LocalBusiness', 'label' => 'Local Business (General)'],
        ['value' => 'AnimalShelter', 'label' => 'Animal Shelter'],
        ['value' => 'AutomotiveBusiness', 'label' => 'Automotive Business'],
        ['value' => 'AutoBodyShop', 'label' => 'Auto Body Shop'],
        ['value' => 'AutoDealer', 'label' => 'Auto Dealer'],
        ['value' => 'AutoPartsStore', 'label' => 'Auto Parts Store'],
        ['value' => 'AutoRental', 'label' => 'Auto Rental'],
        ['value' => 'AutoRepair', 'label' => 'Auto Repair'],
        ['value' => 'AutoWash', 'label' => 'Auto Wash'],
        ['value' => 'GasStation', 'label' => 'Gas Station'],
        ['value' => 'MotorcycleDealer', 'label' => 'Motorcycle Dealer'],
        ['value' => 'MotorcycleRepair', 'label' => 'Motorcycle Repair'],
        ['value' => 'ChildCare', 'label' => 'Child Care'],
        ['value' => 'Dentist', 'label' => 'Dentist'],
        ['value' => 'DryCleaningOrLaundry', 'label' => 'Dry Cleaning / Laundry'],
        ['value' => 'EmergencyService', 'label' => 'Emergency Service'],
        ['value' => 'EmploymentAgency', 'label' => 'Employment Agency'],
        ['value' => 'EntertainmentBusiness', 'label' => 'Entertainment Business'],
        ['value' => 'AdultEntertainment', 'label' => 'Adult Entertainment'],
        ['value' => 'AmusementPark', 'label' => 'Amusement Park'],
        ['value' => 'ArtGallery', 'label' => 'Art Gallery'],
        ['value' => 'Casino', 'label' => 'Casino'],
        ['value' => 'ComedyClub', 'label' => 'Comedy Club'],
        ['value' => 'MovieTheater', 'label' => 'Movie Theater'],
        ['value' => 'NightClub', 'label' => 'Night Club'],
        ['value' => 'FinancialService', 'label' => 'Financial Service'],
        ['value' => 'AccountingService', 'label' => 'Accounting Service'],
        ['value' => 'Bank', 'label' => 'Bank'],
        ['value' => 'InsuranceAgency', 'label' => 'Insurance Agency'],
        ['value' => 'FoodEstablishment', 'label' => 'Food Establishment'],
        ['value' => 'Bakery', 'label' => 'Bakery'],
        ['value' => 'BarOrPub', 'label' => 'Bar / Pub'],
        ['value' => 'Brewery', 'label' => 'Brewery'],
        ['value' => 'CafeOrCoffeeShop', 'label' => 'Cafe / Coffee Shop'],
        ['value' => 'FastFoodRestaurant', 'label' => 'Fast Food Restaurant'],
        ['value' => 'IceCreamShop', 'label' => 'Ice Cream Shop'],
        ['value' => 'Restaurant', 'label' => 'Restaurant'],
        ['value' => 'Winery', 'label' => 'Winery'],
        ['value' => 'GovernmentOffice', 'label' => 'Government Office'],
        ['value' => 'HealthAndBeautyBusiness', 'label' => 'Health & Beauty Business'],
        ['value' => 'BeautySalon', 'label' => 'Beauty Salon'],
        ['value' => 'DaySpa', 'label' => 'Day Spa'],
        ['value' => 'HairSalon', 'label' => 'Hair Salon'],
        ['value' => 'HealthClub', 'label' => 'Health Club'],
        ['value' => 'NailSalon', 'label' => 'Nail Salon'],
        ['value' => 'TattooParlor', 'label' => 'Tattoo Parlor'],
        ['value' => 'HomeAndConstructionBusiness', 'label' => 'Home & Construction'],
        ['value' => 'Electrician', 'label' => 'Electrician'],
        ['value' => 'GeneralContractor', 'label' => 'General Contractor'],
        ['value' => 'HVACBusiness', 'label' => 'HVAC Business'],
        ['value' => 'HousePainter', 'label' => 'House Painter'],
        ['value' => 'Locksmith', 'label' => 'Locksmith'],
        ['value' => 'MovingCompany', 'label' => 'Moving Company'],
        ['value' => 'Plumber', 'label' => 'Plumber'],
        ['value' => 'RoofingContractor', 'label' => 'Roofing Contractor'],
        ['value' => 'InternetCafe', 'label' => 'Internet Cafe'],
        ['value' => 'LegalService', 'label' => 'Legal Service'],
        ['value' => 'Attorney', 'label' => 'Attorney'],
        ['value' => 'Notary', 'label' => 'Notary'],
        ['value' => 'Library', 'label' => 'Library'],
        ['value' => 'LodgingBusiness', 'label' => 'Lodging Business'],
        ['value' => 'BedAndBreakfast', 'label' => 'Bed & Breakfast'],
        ['value' => 'Campground', 'label' => 'Campground'],
        ['value' => 'Hostel', 'label' => 'Hostel'],
        ['value' => 'Hotel', 'label' => 'Hotel'],
        ['value' => 'Motel', 'label' => 'Motel'],
        ['value' => 'Resort', 'label' => 'Resort'],
        ['value' => 'MedicalBusiness', 'label' => 'Medical Business'],
        ['value' => 'Optician', 'label' => 'Optician'],
        ['value' => 'Pharmacy', 'label' => 'Pharmacy'],
        ['value' => 'Physician', 'label' => 'Physician'],
        ['value' => 'ProfessionalService', 'label' => 'Professional Service'],
        ['value' => 'RadioStation', 'label' => 'Radio Station'],
        ['value' => 'RealEstateAgent', 'label' => 'Real Estate Agent'],
        ['value' => 'RecyclingCenter', 'label' => 'Recycling Center'],
        ['value' => 'SelfStorage', 'label' => 'Self Storage'],
        ['value' => 'ShoppingCenter', 'label' => 'Shopping Center'],
        ['value' => 'SportsActivityLocation', 'label' => 'Sports Activity Location'],
        ['value' => 'BowlingAlley', 'label' => 'Bowling Alley'],
        ['value' => 'ExerciseGym', 'label' => 'Exercise Gym'],
        ['value' => 'GolfCourse', 'label' => 'Golf Course'],
        ['value' => 'SkiResort', 'label' => 'Ski Resort'],
        ['value' => 'SportsClub', 'label' => 'Sports Club'],
        ['value' => 'StadiumOrArena', 'label' => 'Stadium / Arena'],
        ['value' => 'TennisComplex', 'label' => 'Tennis Complex'],
        ['value' => 'Store', 'label' => 'Store'],
        ['value' => 'BikeStore', 'label' => 'Bike Store'],
        ['value' => 'BookStore', 'label' => 'Book Store'],
        ['value' => 'ClothingStore', 'label' => 'Clothing Store'],
        ['value' => 'ComputerStore', 'label' => 'Computer Store'],
        ['value' => 'ConvenienceStore', 'label' => 'Convenience Store'],
        ['value' => 'DepartmentStore', 'label' => 'Department Store'],
        ['value' => 'ElectronicsStore', 'label' => 'Electronics Store'],
        ['value' => 'Florist', 'label' => 'Florist'],
        ['value' => 'FurnitureStore', 'label' => 'Furniture Store'],
        ['value' => 'GardenStore', 'label' => 'Garden Store'],
        ['value' => 'GroceryStore', 'label' => 'Grocery Store'],
        ['value' => 'HardwareStore', 'label' => 'Hardware Store'],
        ['value' => 'HobbyShop', 'label' => 'Hobby Shop'],
        ['value' => 'HomeGoodsStore', 'label' => 'Home Goods Store'],
        ['value' => 'JewelryStore', 'label' => 'Jewelry Store'],
        ['value' => 'LiquorStore', 'label' => 'Liquor Store'],
        ['value' => 'MensClothingStore', 'label' => 'Mens Clothing Store'],
        ['value' => 'MobilePhoneStore', 'label' => 'Mobile Phone Store'],
        ['value' => 'MovieRentalStore', 'label' => 'Movie Rental Store'],
        ['value' => 'MusicStore', 'label' => 'Music Store'],
        ['value' => 'OfficeEquipmentStore', 'label' => 'Office Equipment Store'],
        ['value' => 'OutletStore', 'label' => 'Outlet Store'],
        ['value' => 'PawnShop', 'label' => 'Pawn Shop'],
        ['value' => 'PetStore', 'label' => 'Pet Store'],
        ['value' => 'ShoeStore', 'label' => 'Shoe Store'],
        ['value' => 'SportingGoodsStore', 'label' => 'Sporting Goods Store'],
        ['value' => 'TireShop', 'label' => 'Tire Shop'],
        ['value' => 'ToyStore', 'label' => 'Toy Store'],
        ['value' => 'WholesaleStore', 'label' => 'Wholesale Store'],
        ['value' => 'TelevisionStation', 'label' => 'Television Station'],
        ['value' => 'TouristInformationCenter', 'label' => 'Tourist Information Center'],
        ['value' => 'TravelAgency', 'label' => 'Travel Agency'],
        ['value' => 'VeterinaryCare', 'label' => 'Veterinary Care'],
    ],
];
