package pf::Authentication::Action;

=head1 NAME

pf::Authentication::Action

=head1 DESCRIPTION

=cut

use Moose;

use pf::Authentication::constants;

has 'type' => (isa => 'Str', is => 'rw', required => 1);
has 'value' => (isa => 'Str', is => 'rw', required => 0);

sub availableActions {
    return [
            $Actions::MARK_AS_SPONSOR,
            $Actions::SET_ACCESS_LEVEL,
            $Actions::SET_ROLE,
            $Actions::SET_UNREG_DATE,
           ];
}

=back

=head1 COPYRIGHT

Copyright (C) 2012 Inverse inc.

=head1 LICENSE 

This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301,
USA.

=cut

__PACKAGE__->meta->make_immutable;
1;

# vim: set shiftwidth=4:
# vim: set expandtab:
# vim: set backspace=indent,eol,start: