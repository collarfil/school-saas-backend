<?php
// app/Console/Commands/GenerateSchoolSubdomains.php

namespace App\Console\Commands;

use App\Modules\Core\Models\School;
use Illuminate\Console\Command;
use Illuminate\Support\Str;

class GenerateSchoolSubdomains extends Command
{
    protected $signature = 'schools:generate-subdomains';
    protected $description = 'Generate subdomains for all existing schools';

    public function handle()
    {
        $schools = School::whereNull('subdomain')->get();
        
        $this->info("Found {$schools->count()} schools without subdomains");
        
        $bar = $this->output->createProgressBar($schools->count());
        
        foreach ($schools as $school) {
            $subdomain = Str::slug($school->name);
            $originalSubdomain = $subdomain;
            $counter = 1;
            
            while (School::where('subdomain', $subdomain)->where('id', '!=', $school->id)->exists()) {
                $subdomain = $originalSubdomain . '-' . $counter;
                $counter++;
            }
            
            $school->subdomain = $subdomain;
            $school->save();
            
            $bar->advance();
        }
        
        $bar->finish();
        $this->newLine();
        $this->info('Subdomains generated successfully!');
    }
}