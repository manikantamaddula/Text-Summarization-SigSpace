#include "WIKI2XML.h"

#include <stdio.h>
#include <time.h>

void read_from_file ( istream &infile , vector <string> &lines )
	{
    int a ;
    string line ;
    lines.clear () ;
    while (getline(infile,line,'\n'))
        {
        lines.push_back (line);
        }
	}    

int main(int argc, char *argv[])
    {
    ifstream infile ( "test.txt" , ios::in ) ;
    vector <string> lines ;
    read_from_file ( infile , lines ) ;

    int c1 = clock () ;
        
//    for ( int a = 0 ; a < 2200 ; a++ )
//    	{
        WIKI2XML w2x ( lines ) ;
        w2x.parse () ;
//        }
        
    int c2 = clock () ;
    
    cout << ( c2 - c1 ) << " clock" << endl ;
    cout << (float) ( c2 - c1 ) / CLK_TCK << " seconds" << endl ;
    
    ofstream out ( "test.xml" , ios::out ) ;
    cout << w2x.get_xml () << endl ;
    
    system("PAUSE");	
    return 0;
    }
